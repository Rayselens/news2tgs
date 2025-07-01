<?php
    use Longman\TelegramBot\Request;
    use Longman\TelegramBot\Telegram;
    use Longman\TelegramBot\Exception\TelegramException;

    class UsersJoin
    {
        private $webhookUrl;
        private $telegramHook;
        private $botToken;
        private $channelId;
        private $dbConnection;
        private $usersJson;
        private $bot;
        private $botUsername;

        private $usersGlobalArray = [];

        public function __construct($webhookUrl, $telegramHook, $botToken, $channelId, $dbConnection, $usersJson, $botUsername)
        {
            $this->webhookUrl = $webhookUrl;
            $this->telegramHook = $telegramHook;
            $this->botToken = $botToken;
            $this->channelId = $channelId;
            $this->dbConnection = $dbConnection;
            $this->usersJson = $usersJson;
            $this->botUsername = $botUsername;
            $this->bot = new Telegram($this->botToken, $this->botUsername);
        }

        public function formatPhone($unformattedNumber)
        {
            $unformattedNumber = trim($unformattedNumber);
            $formattedNumber = preg_replace(
                array(
                    '/[\+]?([7|8])[-|\s]?\([-|\s]?(\d{3})[-|\s]?\)[-|\s]?(\d{3})[-|\s]?(\d{2})[-|\s]?(\d{2})/',
                    '/[\+]?([7|8])[-|\s]?(\d{3})[-|\s]?(\d{3})[-|\s]?(\d{2})[-|\s]?(\d{2})/',
                    '/[\+]?([7|8])[-|\s]?\([-|\s]?(\d{4})[-|\s]?\)[-|\s]?(\d{2})[-|\s]?(\d{2})[-|\s]?(\d{2})/',
                    '/[\+]?([7|8])[-|\s]?(\d{4})[-|\s]?(\d{2})[-|\s]?(\d{2})[-|\s]?(\d{2})/',	
                    '/[\+]?([7|8])[-|\s]?\([-|\s]?(\d{4})[-|\s]?\)[-|\s]?(\d{3})[-|\s]?(\d{3})/',
                    '/[\+]?([7|8])[-|\s]?(\d{4})[-|\s]?(\d{3})[-|\s]?(\d{3})/',					
                ), 
                array(
                    '+7$2$3$4$5', 
                    '+7$2$3$4$5', 
                    '+7$2$3$4$5', 
                    '+7$2$3$4$5', 	
                    '+7$2$3$4', 
                    '+7$2$3$4', 
                ), 
                $unformattedNumber
            );
            
            return $formattedNumber;
        }

        private function getUserField($update)
        {
            $userTgPhone = $this->formatPhone($update['message']['contact']['phone_number']);
            $userTgId = $update['message']['contact']['user_id'];
            $userBitrixId = null;
            $userFio = null;
            $userBitrixMobilePhone = null;
            $userBitrixWorkPhone = null;
            $userBitrixInnerPhone = null;
            $userBitrixActive = null;

            foreach ($this->usersJson['result'] as $item) {
                if($this->formatPhone($item['PERSONAL_MOBILE']) == $userTgPhone 
                || $this->formatPhone($item['WORK_PHONE']) == $userTgPhone 
                || $this->formatPhone($item['UF_PHONE_INNER']) == $userTgPhone) {
                    $userBitrixId = $item["ID"];
                    $userFio = $item['NAME'] . ' ' . $item['LAST_NAME'];
                    $userBitrixMobilePhone = $this->formatPhone($item['PERSONAL_MOBILE']);
                    $userBitrixWorkPhone = $this->formatPhone($item['WORK_PHONE']);
                    $userBitrixInnerPhone = $this->formatPhone($item['UF_PHONE_INNER']);
                    $userBitrixActive = $item['ACTIVE'];
                    break;
                }
            }

            return [
                "employee_telegram_id" => $userTgId,
                "employee_fio" => $userFio,
                "employee_bitrix_id" => $userBitrixId,
                "employee_telegram_phone" => $userTgPhone,
                "employee_bitrix_mobile_phone" => $userBitrixMobilePhone,
                "employee_bitrix_work_phone" => $userBitrixWorkPhone,
                "employee_bitrix_inner_phone" => $userBitrixInnerPhone,
                "employee_bitrix_active" => $userBitrixActive,
            ];
        }

        private function createPersonalInviteLink($chatId)
        {
            $result = Request::createChatInviteLink([
                "chat_id" => $chatId,
                "member_limit" => 1
            ]);
            return json_decode($result, true)['result']['invite_link'];
        }

        private function handleStartCommand($update)
        {
            return Request::sendMessage([
                'chat_id' => $update['message']['chat']['id'],
                'text' => "Для получения доступа к телеграм-каналу необходимо сообщить боту номер телефона. Для успешного вступления, на вашем аккаунте Битрикс должен быть указан мобильный телефон.",
                'reply_markup' => json_encode([
                    'keyboard' => [
                        [
                            [
                                'text' => 'Сообщить боту номер телефона',
                                'request_contact' => true,
                                'one_time_keyboard' => true
                            ]
                        ]
                    ],
                    'one_time_keyboard' => true,
                    'remove_keyboard' => true
                ])
            ]);
        }

        private function handleContactMessage($update)
        {
            $isUserInBitrix = false;
            $contactPhone = $this->formatPhone($update['message']['contact']['phone_number']);

            foreach ($this->usersJson['result'] as $item) {
                if($this->formatPhone($item['PERSONAL_MOBILE']) == $contactPhone 
                || $this->formatPhone($item['WORK_PHONE']) == $contactPhone 
                || $this->formatPhone($item['UF_PHONE_INNER']) == $contactPhone) {
                    $isUserInBitrix = true;
                    break;
                }
            }

            if ($isUserInBitrix == true) {
                $this->usersGlobalArray = $this->getUserField($update);

                $addUserCheck = "SELECT * from employee WHERE employee_telegram_id = '{$this->usersGlobalArray['employee_telegram_id']}';";
                $addUserCheckResult = pg_query($this->dbConnection, $addUserCheck);
                
                if(pg_num_rows($addUserCheckResult) == 0) {
                    $dbAddUserQuery = "INSERT INTO employee (
                        employee_telegram_id, 
                        employee_bitrix_id, 
                        employee_fio, 
                        employee_telegram_phone, 
                        employee_bitrix_mobile_phone, 
                        employee_bitrix_work_phone, 
                        employee_bitrix_inner_phone, 
                        employee_active
                    ) VALUES (
                        '{$this->usersGlobalArray['employee_telegram_id']}', 
                        '{$this->usersGlobalArray['employee_bitrix_id']}', 
                        '{$this->usersGlobalArray['employee_fio']}', 
                        '{$this->usersGlobalArray['employee_telegram_phone']}', 
                        '{$this->usersGlobalArray['employee_bitrix_mobile_phone']}', 
                        '{$this->usersGlobalArray['employee_bitrix_work_phone']}', 
                        '{$this->usersGlobalArray['employee_bitrix_inner_phone']}', 
                        '{$this->usersGlobalArray['employee_bitrix_active']}'
                    );";

                    $dbAddUserResult = pg_query($this->dbConnection, $dbAddUserQuery);
                }

                return Request::sendMessage([
                    'chat_id' => $update['message']['chat']['id'],
                    'parse_mode' => 'html',
                    'text' => "Готово! Индивидуальная ссылка на вступление в канал с новостями: \n" . $this->createPersonalInviteLink($this->channelId),
                    'reply_markup' => json_encode([
                        'ReplyKeyboardRemove' => [],
                        'one_time_keyboard' => true,
                        'remove_keyboard' => true
                    ])
                ]);
            } else {
                return Request::sendMessage([
                    'chat_id' => $update['message']['chat']['id'],
                    'parse_mode' => 'html',
                    'text' => "Пользователь с данным номером телефона не найден! Войдите в аккаунт Битрикс, проверьте на корректность номер вашего мобильного телефона и попробуйте еще раз!",
                ]);
            }
        }

        private function handleDefaultMessage($update)
        {
            return Request::sendMessage([
                'chat_id' => $update['message']['chat']['id'],
                'parse_mode' => 'html',
                'text' => "Хорошего дня!",
                'reply_markup' => json_encode([
                    'ReplyKeyboardRemove' => [],
                    'one_time_keyboard' => true,
                    'remove_keyboard' => true
                ])
            ]);
        }

        public function processUpdate($update)
        {
            if ($update['message']['text'] == '/start') {
                return $this->handleStartCommand($update);
            } elseif (isset($update['message']['contact'])) {
                return $this->handleContactMessage($update);
            } else {
                return $this->handleDefaultMessage($update);
            }
        }

        public function run()
        {
            try {
                $result = $this->bot->setWebhook($this->telegramHook);
                
                if ($result->isOk()) {
                    $upd = file_put_contents('./log.txt', file_get_contents('php://input'));
                    $update = json_decode(file_get_contents('./log.txt'), JSON_OBJECT_AS_ARRAY);
                    
                    return $this->processUpdate($update);
                }
            } catch (TelegramException $e) {
                error_log($e->getMessage());
                return false;
            }
        }
    }
?>