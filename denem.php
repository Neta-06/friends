<?php
$temperature = 15;
$windSpeed = 8;
$isIdeal = ($temperature > ?) && ($windSpeed < ?);
// Aşağıdaki satırı değiştirmeyin
echo "Ideal weather: " . ($isIdeal ? "true" : "false");
?>