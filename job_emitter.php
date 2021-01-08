<?php

require_once  '../vendor/autoload.php';
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

$connection = new AMQPStreamConnection('localhost', 5672, 'guest', 'guest');
$channel = $connection->channel();

$channel->exchange_declare('time_jobs', 'direct', false, false, false);

$type = $argv[0]; // cron_events or cron_frame

$data = implode(' ', array_slice($argv, 1)); //arguments list

$msg = new AMQPMessage($data);

$channel->basic_publish($msg, 'time_jobs', $type);

$channel->close();
$connection->close();
