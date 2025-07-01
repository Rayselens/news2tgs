<?php
    class NewsBuilder
    {
        private $webHookUrl;
        private $botToken;
        private $channelId;
        private $newsJson;
        private $usersJson;

        public function __construct(string $webHookUrl, string $botToken, string $channelId, array $newsJson, array $usersJson)
        {
            $this->webHookUrl = $webHookUrl;
            $this->botToken = $botToken;
            $this->channelId = $channelId;
            $this->newsJson = $newsJson;
            $this->usersJson = $usersJson;
        }

        public function formatAndSendMessage(): void
        {
            if ($this->requestCheck() == false) {
                return;
            }

            $newsData = $this->storeWebhookData();
            $newsMessage = $this->createNewsMessage($newsData);
            $attaches = [];
            try {
                $attaches = $this->storeAttaches($newsData['attached_file']);
            } catch (\Throwable $th) {
                $attaches = [];
            }
            $this->sendToTelegram($newsMessage, $attaches);
        }

        private function requestCheck(): bool
        {
            if ($_SERVER['HTTP_USER_AGENT'] == "Bitrix24 Webhook Engine" 
                && $this->newsJson['result'][0]['HAS_SOCNET_ALL'] == "Y") {
                return true;
            }
            return false;
        }

        private function storeWebhookData(): array
        {
            $news = $this->newsJson['result'][0];
            $header = $news['TITLE'];
            $content = $news['DETAIL_TEXT'];

            if ($header == $content) {
                $header = 'Новость';
            }

            if ($content == '[B][/B]') {
                $content = '';
            } else {
                $content .= "\n \n";
            }

            $authorName = $this->getAuthorName($news['AUTHOR_ID']);

            return [
                'header' => $header,
                'content' => $content,
                'date_publish' => new DateTimeImmutable($news['DATE_PUBLISH']),
                'author_id' => $news['AUTHOR_ID'],
                'author_name' => $authorName,
                'attached_file' => $news['FILES'],
                'id' => $news['ID']
            ];
        }

        private function getAuthorName(string $authorId): string
        {
            foreach ($this->usersJson['result'] as $user) {
                if ($user['ID'] == $authorId) {
                    return $user["NAME"] . ' ' . $user["LAST_NAME"];
                }
            }
            return '';
        }

        private function createNewsMessage(array $newsData): string
        {
            $blogpostLink = parse_url($this->webHookUrl);
            
            $news_message = "📣  <b>" . $newsData['header'] . "</b> \n \n " . 
                $newsData['content'] . 
                "📅  " . $newsData['date_publish']->format("d.m.Y") . "\n" . 
                "👤  " . $newsData['author_name'] . "\n \n Ссылка на новость: " . 
                $blogpostLink['scheme'] . "://" . $blogpostLink['host'] . 
                "/company/personal/user/" . $newsData['author_id'] . 
                "/blog/" . $newsData['id'] . "/";
            return $news_message;
        }

        private function storeAttaches(array $attachedFiles): array
        {
            $attaches = [];
            foreach ($attachedFiles as $item) {
                $attach = CRest::call(
                    'disk.attachedObject.get',
                    [
                        "id" => $item
                    ]
                );

                $filename = $attach['result']['NAME'];
                $file_download_link = $attach['result']['DOWNLOAD_URL'];
                array_push($attaches, ['FILE_NAME' => $filename, 'DOWNLOAD_URL' => $file_download_link]);
            }
            
            return $attaches;
        }

        private function sendToTelegram(string $message, array $attaches): void
        {
            if (count($attaches) == 1) {
                $news_query = array(
                    "chat_id" => $this->channelId,
                    "caption" => $message,
                    "document" => curl_file_create($attaches[0]['DOWNLOAD_URL'], 'image/jpeg', $attaches[0]['FILE_NAME']),
                    "parse_mode" => 'html'
                );
                $curl_url = curl_init('https://api.telegram.org/bot' . $this->botToken . '/sendDocument?');
            } else if (count($attaches) > 1) {
                $news_query = array(
                    "chat_id" => $this->channelId,
                );

                $news_media = [];
                for ($i=0; $i < count($attaches); $i++) { 
                    array_push($news_media, ["type" => 'document', 'media' => "attach://{$i}"]);
                }

                $news_media[count($news_media) - 1]['caption'] = $message;
                $news_media[count($news_media) - 1]['parse_mode'] = 'html';
                $news_query["media"] = json_encode($news_media);

                for ($i=0; $i < count($attaches); $i++) { 
                    $news_query["{$i}"] = curl_file_create($attaches[$i]['DOWNLOAD_URL'], 'image/jpeg', $attaches[$i]['FILE_NAME']);
                }
                $curl_url = curl_init('https://api.telegram.org/bot' . $this->botToken . '/sendMediaGroup?');
            } else{
                $news_query = array(
                    "chat_id" => $this->channelId,
                    "text" => $message,
                    "parse_mode" => 'html'
                );
                $curl_url = curl_init('https://api.telegram.org/bot' . $this->botToken . '/sendMessage?');
            }

            curl_setopt($curl_url, CURLOPT_POST, true);
            curl_setopt($curl_url, CURLOPT_POSTFIELDS, $news_query);
            curl_setopt($curl_url, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl_url, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl_url, CURLOPT_HEADER, false);

            $send_message_query = curl_exec($curl_url);
            curl_close($curl_url);
        }
    }
?>