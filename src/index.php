<?php
    require_once (__DIR__.'/crest/crest.php');
    include __DIR__.'/vendor/autoload.php';
    
    include 'environment.php';
    include 'db_connect.php';
    include 'webhooks.php';
    include 'news_builder.php';
    include 'users_join.php';
    include 'users_control.php';

    $databaseConnection = new DatabaseConnection($db_host, $db_port, $db_name, $db_user, $db_password);

    $c_rest_webhook = new Webhook();

    $news_builder = new NewsBuilder(C_REST_WEB_HOOK_URL, $bot_token, $channel_id, $c_rest_webhook->getNewsJson(), $c_rest_webhook->getUsersJson());
    $news_builder->formatAndSendMessage();

    $usersJoin = new UsersJoin(C_REST_WEB_HOOK_URL, $c_rest_webhook->getTelegramHookUrl('https://urtk-test.profintel.net/'), $bot_token, $channel_id, $databaseConnection->DbConnection(), $c_rest_webhook->getUsersJson(), $c_rest_webhook->getBotUsername($bot_token));
    $usersJoin->run();

    $usersControl = new UsersControl($databaseConnection->DbConnection(), $channel_id, $c_rest_webhook->getUserFilterJson());
    $usersControl->processInactiveUsers();
?>
