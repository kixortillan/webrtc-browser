<?php
require __DIR__ . '/vendor/autoload.php';

$options = array(
    'cluster' => 'ap1',
    'encrypted' => true,
);
$pusher = new Pusher\Pusher(
    'cc652d98262ccd7cb9df',
    '63a0b1eca7f786fb714c',
    '482183',
    $options
);

$data = $_GET;

//echo var_dump($data);

$pusher->trigger('my-channel', $data['on'], $data);
