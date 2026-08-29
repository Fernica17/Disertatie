document.addEventListener('DOMContentLoaded', function() {
    const avatarInput = document.querySelector('.js-avatar-input');
    const previewContainer = document.getElementById('avatarPreview');

    if (avatarInput && previewContainer) {
        avatarInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const reader = new FileReader();

                reader.onload = function(e) {
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.alt = 'Preview';
                    previewContainer.textContent = '';
                    previewContainer.appendChild(img);
                }

                reader.readAsDataURL(this.files[0]);
            }
        });
    }
});
