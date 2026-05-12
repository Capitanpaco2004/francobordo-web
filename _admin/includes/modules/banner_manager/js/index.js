$(document).ready(function() {
    // Save form button
    $('#saveform').on('click', function(e) {
        e.preventDefault();
        $('#saveform-send').submit();
    });

    // Preview image before upload
    $('input[type="file"]').on('change', function(e) {
        var input = this;
        var container = $(this).closest('.column');

        if (input.files && input.files[0]) {
            var reader = new FileReader();

            reader.onload = function(e) {
                // Remove existing preview
                container.find('.banner-preview').remove();

                // Add new preview
                container.append('<img src="' + e.target.result + '" class="banner-preview" />');
            };

            reader.readAsDataURL(input.files[0]);
        }
    });
});
