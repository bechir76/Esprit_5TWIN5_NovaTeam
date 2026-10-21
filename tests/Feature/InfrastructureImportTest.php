<?php

namespace Tests\Feature;

use App\Enums\InfrastructureStatus;
use App\Models\Infrastructure;
use App\Models\User;
use App\Models\Zone;
use App\Notifications\InfrastructureBecameCritical;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** A9 : import CSV des infrastructures (mêmes règles que le formulaire, rapport par ligne, transaction, fichier modèle). */
class InfrastructureImportTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = 'code;nom;type;zone;materiau;capacite;unite;annee_mise_en_service;pression_nominale;etat;derniere_inspection;latitude;longitude;public;description';

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->manager = User::factory()->gestionnaire()->create();
        Zone::factory()->create(['name' => 'Ariana Nord']);
    }

    private function csv(array $lines, string $name = 'import.csv'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "\xEF\xBB\xBF".implode("\n", $lines)."\n");
    }

    private function import(UploadedFile $file, bool $strict = false)
    {
        return $this->actingAs($this->manager)->post(route('admin.infrastructures.import.store'), ['file' => $file, 'strict' => $strict ? '1' : '0']);
    }

    public function test_template_can_be_downloaded_and_imported_as_is(): void
    {
        $response = $this->actingAs($this->manager)->get(route('admin.infrastructures.import.template'));
        $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('modele-import-infrastructures.csv', $response->headers->get('content-disposition'));

        $content = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF".self::HEADER, $content);

        $this->import(UploadedFile::fake()->createWithContent('modele.csv', $content))
            ->assertRedirect(route('admin.infrastructures.import'))
            ->assertSessionHas('status', '2 infrastructure(s) importée(s).');

        $reservoir = Infrastructure::firstWhere('code', 'RES-ARI-901');
        $this->assertSame(InfrastructureStatus::ASurveiller, $reservoir->status);
        $this->assertSame('m3', $reservoir->capacity_unit->value);
        $this->assertSame('2026-03-15', $reservoir->last_inspection_at->format('Y-m-d'));
        $this->assertSame(4.5, (float) $reservoir->nominal_pressure);
        $this->assertTrue($reservoir->is_public);
        $this->assertFalse(Infrastructure::firstWhere('code', 'CND-ARI-902')->is_public);

        // Nom de zone saisi par le personnel : neutralisé dans le modèle (injection de formule)
        Zone::query()->update(['name' => '=HYPERLINK("http://exemple.test")']);
        $content = $this->actingAs($this->manager)->get(route('admin.infrastructures.import.template'))->streamedContent();
        $this->assertStringContainsString(';"\'=HYPERLINK(""http://exemple.test"")";', $content);
    }

    public function test_each_line_is_validated_like_the_form_and_the_report_lists_errors_by_line(): void
    {
        Infrastructure::factory()->for(Zone::factory()->create(['name' => 'Zone témoin']))->create(['code' => 'RES-TUN-001']);

        $this->import($this->csv([
            self::HEADER,
            'res-ari-010;Réservoir valide;Réservoir;Ariana Nord;PEHD;5000;m3;2001;;bon;;;;oui;',
            'MAUVAIS;Code invalide;reservoir;Ariana Nord;;100;m3;2001;;bon;;;;;',
            'RES-ARI-011;Zone inconnue;reservoir;Atlantide;;100;m3;2001;;bon;;;;;',
            'RES-TUN-001;Code déjà pris;reservoir;Ariana Nord;;100;m3;2001;;bon;;;;;',
            'RES-ARI-010;Doublon dans le fichier;reservoir;Ariana Nord;;100;m3;2001;;bon;;;;;',
            '',
            'PMP-ARI-012;Station critique;Station de pompage;Ariana Nord;Acier;120;L/s;3000;;critique;;;;;',
        ]))->assertSessionHas('error', '1 infrastructure(s) importée(s), 5 ligne(s) en erreur.');

        $report = session('import_report');
        $this->assertSame([['line' => 2, 'code' => 'RES-ARI-010', 'name' => 'Réservoir valide']], $report['imported']);
        $errors = collect($report['errors'])->pluck('messages', 'line');
        $this->assertSame([3, 4, 5, 6, 8], $errors->keys()->all());
        $this->assertContains('Le code doit respecter le format ABC-ABC-000 (type, gouvernorat, numéro), par exemple RES-ARI-004.', $errors[3]);
        $this->assertContains('La zone choisie n\'existe pas.', $errors[4]);
        $this->assertContains('Ce code ouvrage est déjà attribué à une autre infrastructure.', $errors[5]);
        $this->assertContains('Ce code ouvrage est déjà attribué à une autre infrastructure.', $errors[6]);
        $this->assertStringContainsString('année de mise en service', $errors[8][0]);
        $this->assertSame(2, Infrastructure::count());

        $this->actingAs($this->manager)->get(route('admin.infrastructures.import'))
            ->assertSee('Rapport d\'import', false)
            ->assertSeeInOrder(['2', 'Importée', 'RES-ARI-010', '3', 'Erreur', 'Le code doit respecter']);
    }

    public function test_all_or_nothing_mode_rolls_back_the_transaction(): void
    {
        $this->import($this->csv([
            self::HEADER,
            'RES-ARI-010;Réservoir valide;reservoir;Ariana Nord;;5000;m3;2001;;bon;;;;;',
            'RES-ARI-011;Zone inconnue;reservoir;Atlantide;;100;m3;2001;;bon;;;;;',
        ]), strict: true)->assertSessionHas('error', 'Import annulé (tout ou rien) : 1 ligne(s) en erreur, aucune infrastructure créée.');

        $this->assertTrue(session('import_report')['rolled_back']);
        $this->assertSame(0, Infrastructure::count());
    }

    public function test_comma_separator_windows_1252_and_critical_alert(): void
    {
        $content = mb_convert_encoding("code,nom,type,zone,capacite,annee_mise_en_service,etat\nRES-ARI-020,Réservoir Ennasr,Réservoir,ariana nord,\"6000,5\",1990,Critique\n", 'Windows-1252', 'UTF-8');

        $this->import(UploadedFile::fake()->createWithContent('excel.csv', $content))->assertSessionHas('status');

        $infrastructure = Infrastructure::firstWhere('code', 'RES-ARI-020');
        $this->assertSame('Réservoir Ennasr', $infrastructure->name);
        $this->assertSame(6000.5, (float) $infrastructure->capacity);
        Notification::assertSentTo($this->manager, InfrastructureBecameCritical::class);
    }

    public function test_file_is_checked_before_import(): void
    {
        $this->import(UploadedFile::fake()->create('gros.csv', 3000))->assertSessionHasErrors(['file' => 'Le fichier ne doit pas dépasser 2 Mo.']);
        $this->import(UploadedFile::fake()->create('photo.png', 10, 'image/png'))->assertSessionHasErrors('file');
        $this->actingAs($this->manager)->post(route('admin.infrastructures.import.store'))->assertSessionHasErrors(['file' => 'Choisissez un fichier CSV.']);

        $this->import($this->csv(['code;nom;couleur', 'X;Y;Z']));
        $this->assertSame([['line' => 1, 'messages' => ['Colonnes obligatoires manquantes : type, zone, capacite, annee_mise_en_service, etat. Utilisez le fichier modèle.']]], session('import_report')['errors']);

        $this->import($this->csv([self::HEADER]));
        $this->assertSame('Le fichier ne contient aucune ligne d\'infrastructure.', session('import_report')['errors'][0]['messages'][0]);
        $this->assertSame(0, Infrastructure::count());
    }

    public function test_import_is_reserved_to_admins_and_managers(): void
    {
        $file = $this->csv([self::HEADER, 'RES-ARI-010;Réservoir;reservoir;Ariana Nord;;5000;m3;2001;;bon;;;;;']);

        foreach ([User::factory()->technicien()->create(), User::factory()->citoyen()->create()] as $user) {
            $this->actingAs($user)->get(route('admin.infrastructures.import'))->assertForbidden();
            $this->actingAs($user)->get(route('admin.infrastructures.import.template'))->assertForbidden();
            $this->actingAs($user)->post(route('admin.infrastructures.import.store'), ['file' => $file])->assertForbidden();
        }

        $this->assertSame(0, Infrastructure::count());
        $this->actingAs($this->manager)->get(route('admin.infrastructures.import'))->assertOk()->assertSee('Télécharger le fichier modèle');
    }
}
