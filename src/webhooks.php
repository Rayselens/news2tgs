<?php
    class Webhook
    {
        private $news_json;
        private $users_json;
        private $user_filter;
        private $bot_username;
        private $telegramHookUrl;

        public function getNewsJson(){
            $news_json = CRest::call(
                'log.blogpost.get', []
            );
            return $news_json;
        }

        public function getUsersJson(){
            $users_json = CRest::call(
                'user.get', []
            );
            return $users_json;
        }

        public function getUserFilterJson(){
            $user_filter = CRest::call(
                'user.search',
                [
                    "FILTER" => [
                        "ACTIVE" => false
                    ]
                ]
            );
            return $user_filter;
        }

        public function getBotUsername($bot_token){
            $bot_username = urldecode('https://api.telegram.org/bot' . $bot_token . '/getMe');
            $bot_username = json_decode(file_get_contents($bot_username), true);
            return $bot_username['result']['first_name'];
        }

        public function getTelegramHookUrl($url){
            $telegramHookUrl = $url;
            return $telegramHookUrl;
        }
    }
?>