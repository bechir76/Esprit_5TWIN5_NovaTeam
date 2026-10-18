{{-- Champ « photo de profil » des formulaires du profil (citoyen et personnel) ; le formulaire doit être en multipart --}}
<div class="flex flex-col gap-2">
  <x-form.input name="avatar" :label="$user->avatar_path ? 'Remplacer la photo de profil' : 'Photo de profil'" type="file" accept="image/jpeg,image/png" hint="JPG ou PNG, 2 Mo maximum." />
  @if ($user->avatar_path)
  <label class="flex items-center gap-2 text-[.86rem]"><input type="checkbox" name="remove_avatar" value="1" @checked(old('remove_avatar'))> Retirer la photo actuelle</label>
  @endif
</div>
