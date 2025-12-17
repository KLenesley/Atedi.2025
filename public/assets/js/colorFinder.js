$( document ).ready(function() {
    
    // Color finder
    $('.color-box').each(function() {
        var color = $(this).data('color');
        $(this).css('background-color', color);
    });

    $(".color-picker").on("keyup", function() {
        var color = $(this).val();
        $('.color-box').attr('data-color', '#'+color);
        $('.color-box').css('background-color', '#'+color);
    });

});