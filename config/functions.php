<?php
function getRandomBackground() {
    $backgrounds = [
        'backgrounds/back1.webp',
        'backgrounds/back2.webp',
        'backgrounds/back3.webp',
        'backgrounds/back4.webp'
    ];
    return $backgrounds[array_rand($backgrounds)];
}
?> 