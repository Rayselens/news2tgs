<?php
    use Longman\TelegramBot\Request;

    class UsersControl
    {
        private $dbConnection;
        private $channelId;
        private $userFilter;

        private $unactiveUsersBitrixIds = [];
        private $DbUnactiveUsersBitrixIds = '';
        private $dbUserList = [];

        public function __construct($dbConnection, $channelId, $userFilter)
        {
            $this->dbConnection = $dbConnection;
            $this->channelId = $channelId;
            $this->userFilter = $userFilter;
        }

        public function processInactiveUsers()
        {
            $this->collectInactiveUsers();
            $this->fetchActiveUsersFromDb();
            $this->banInactiveUsers();
            $this->updateUserStatusInDb();
        }

        private function collectInactiveUsers()
        {
            foreach ($this->userFilter['result'] as $item) {
                array_push($this->unactiveUsersBitrixIds, $item['ID']);
                $this->DbUnactiveUsersBitrixIds .= "'" . "{$item['ID']}" . "', ";
            }
            $this->DbUnactiveUsersBitrixIds = substr($this->DbUnactiveUsersBitrixIds , 0, -2);
        }

        private function fetchActiveUsersFromDb()
        {
            $query = "SELECT * FROM employee WHERE employee_bitrix_id IN ({$this->DbUnactiveUsersBitrixIds}) AND employee_active = true;";
            $result = pg_query($this->dbConnection, $query);
            $this->dbUserList = pg_fetch_all($result);
        }

        private function banInactiveUsers()
        {
            foreach ($this->dbUserList as $user) {
                Request::banChatMember([
                    "chat_id" => $this->channelId,
                    "user_id" => $user['employee_telegram_id']
                ]);
            }
        }

        private function updateUserStatusInDb()
        {
            $query = "UPDATE employee SET employee_active = false WHERE employee_bitrix_id IN ({$this->DbUnactiveUsersBitrixIds})";
            pg_query($this->dbConnection, $query);
        }
    }
?>