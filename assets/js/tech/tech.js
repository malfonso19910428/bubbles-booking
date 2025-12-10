// Preview de la foto de perfil en el dashboard del técnico
document.addEventListener('DOMContentLoaded', function () {
    var fileInput = document.getElementById('bb_profile_avatar');
    var preview   = document.getElementById('bb_profile_avatar_preview');

    if (!fileInput || !preview) {
        return; // no estamos en la vista de perfil
    }

    fileInput.addEventListener('change', function () {
        var file = this.files && this.files[0];
        if (!file) return;

        // Asegurarnos de que es imagen
        if (!file.type || file.type.indexOf('image/') !== 0) {
            return;
        }

        var reader = new FileReader();
        reader.onload = function (e) {
            preview.innerHTML =
                '<img src="' + e.target.result + '" alt="Profile photo" class="bb-profile-avatar" />';
        };
        reader.readAsDataURL(file);
    });
});
