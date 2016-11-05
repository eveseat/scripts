<?php
/*
This file is part of SeAT

Copyright (C) 2016  Loïc Leuilliot

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License along
with this program; if not, write to the Free Software Foundation, Inc.,
51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
*/

namespace Seat\Migrate {

    use PDO;

    class Script
    {

        /**
         * @var int
         */
        private static $_maxRows = 1000;
        /**
         * @var PDO
         */
        private static $_oldDatabase;
        /**
         * @var PDO
         */
        private static $_newDatabase;

        public static function main($old_host, $old_port, $old_name, $old_user, $old_pass, $new_host, $new_port, $new_name, $new_user, $new_pass)
        {

            echo "\033[0;97m Checking Database Connection...\033[0m\n";
            try {
                echo "\033[0;34m Trying to connect to the OLD SeAT database... \033[0m";
                self::$_oldDatabase = new PDO("mysql:host=" . $old_host . ";port=" . $old_port . ";dbname=" . $old_name, $old_user, $old_pass);
                echo "\033[0;32m [SUCCESS]\033[0m\n";
            } catch (\PDOException $e) {
                echo "\033[0;31m [FAILED]\033[0m\n";

                return 1;
            }

            try {
                echo "\033[0;34m Trying to connect to the NEW SeAT database... \033[0m";
                self::$_newDatabase = new PDO("mysql:host=" . $new_host . ";port=" . $new_port . ";dbname=" . $new_name, $new_user, $new_pass);
                echo "\033[0;32m [SUCCESS]\033[0m\n";
            } catch (\PDOException $e) {
                echo "\033[0;31m [FAILED]\033[0m\n";

                return 1;
            }

            self::initMigratingTable();

            // Wallet migration
            echo "\r\n";
            echo "\033[0;97m Starting Wallet Data Migration...\033[0m\n";
            self::upgradeWalletData();

            // Message migration
            echo "\r\n";
            echo "\033[0;97m Starting Message Data Migration...\033[0m\n";
            self::upgradeMessageData();

            // User migration
            echo "\r\n";
            echo "\033[0;97m Starting User Data Migration... \033[0m\n";
            self::upgradeUserData();

            return 0;
        }

        // Wallet migration

        private static function initMigratingTable()
        {

            $sql_create = "CREATE TABLE IF NOT EXISTS dist_upgrade(`table` VARCHAR(255) PRIMARY KEY, `migrated` TINYINT(1))";

            $q = self::$_newDatabase->prepare($sql_create);
            $q->execute();
        }

        private static function upgradeWalletData()
        {

            // Market Orders Upgrade
            echo "\033[0;34m migrating character_marketorders to character_market_orders...\033[0m        ";
            if (self::upgradeCharacterMarketOrders()) {
                echo "\033[0;32m [SUCCESS]\033[0m\n";
            } else {
                echo "\033[0;31m [FAILED]\033[0m\n";
            }

            echo "\033[0;34m migrating corporation_marketorders to corporation_market_orders...\033[0m        ";
            if (self::upgradeCorporationMarketOrders()) {
                echo "\033[0;32m [SUCCESS]\033[0m\n";
            } else {
                echo "\033[0;31m [FAILED]\033[0m\n";
            }

            // Wallet Journals Upgrade
            echo "\033[0;34m migrating character_walletjournal to character_wallet_journals...\033[0m        ";
            if (self::upgradeCharacterJournal()) {
                echo "\033[0;32m [SUCCESS]\033[0m\n";
            } else {
                echo "\033[0;31m [FAILED]\033[0m\n";
            }

            echo "\033[0;34m migrating corporation_walletjournal to corporation_wallet_journals...\033[0m        ";
            if (self::upgradeCorporationJournal()) {
                echo "\033[0;32m [SUCCESS]\033[0m\n";
            } else {
                echo "\033[0;31m [FAILED]\033[0m\n";
            }

            // Wallet Transactions Upgrade
            echo "\033[0;34m migrating character_wallettransactions to character_wallet_transactions...\033[0m        ";
            if (self::upgradeCharacterTransaction()) {
                echo "\033[0;32m [SUCCESS]\033[0m\n";
            } else {
                echo "\033[0;31m [FAILED]\033[0m\n";
            }

            echo "\033[0;34m migrating corporation_wallettransactions to corporation_wallet_transactions...\033[0m        ";
            if (self::upgradeCorporationTransaction()) {
                echo "\033[0;32m [SUCCESS]\033[0m\n";
            } else {
                echo "\033[0;31m [FAILED]\033[0m\n";
            }
        }

        private static function upgradeCharacterMarketOrders()
        {

            self::initMigratingFlag("character_marketorders");
            if (self::statusMigratingFlag("character_marketorders")) {
                return true;
            }

            $q = self::$_oldDatabase->prepare("SELECT COUNT(*) AS nb_rows FROM character_marketorders");
            $q->execute();
            $nb_rows = $q->fetch(PDO::FETCH_ASSOC)['nb_rows'];
            $q->closeCursor();

            $migrated_line = 0;
            for ($curr_row = 0; $curr_row <= $nb_rows; $curr_row += self::$_maxRows) {
                $rows = CharacterUpgrade::fetchOldMarketOrder(self::$_oldDatabase, $curr_row, self::$_maxRows);
                foreach ($rows as $row) {
                    if (CharacterUpgrade::insertNewMarketOrder(self::$_newDatabase, $row)) {
                        Helper::displayProgress(++$migrated_line, $nb_rows);
                    } else {
                        return false;
                    }
                }
            }

            self::updateMigratingFlag("character_marketorders");

            return true;
        }

        private static function initMigratingFlag($table_name)
        {

            $q = self::$_newDatabase->prepare("INSERT IGNORE INTO `dist_upgrade` (`table`, `migrated`) VALUES (:tbl_name, :flag)");
            $q->bindParam(":tbl_name", $table_name, PDO::PARAM_STR);
            $q->bindValue(":flag", 0);
            $q->execute();
            $q->closeCursor();
        }

        private static function statusMigratingFlag($table_name)
        {

            $q = self::$_newDatabase->prepare("SELECT * FROM `dist_upgrade` WHERE `table` = :tbl_name AND `migrated` = :flag");
            $q->bindValue(":flag", 1, PDO::PARAM_INT);
            $q->bindParam(":tbl_name", $table_name, PDO::PARAM_STR);
            $q->execute();

            if ($q->rowCount()) {
                $q->closeCursor();

                return true;
            } else {
                $q->closeCursor();

                return false;
            }
        }

        private static function updateMigratingFlag($table_name)
        {

            $q = self::$_newDatabase->prepare("UPDATE `dist_upgrade` SET `migrated` = :flag WHERE `table` = :tbl_name");
            $q->bindValue(":flag", 1, PDO::PARAM_INT);
            $q->bindParam(":tbl_name", $table_name, PDO::PARAM_STR);
            $q->execute();
            $q->closeCursor();
        }

        private static function upgradeCorporationMarketOrders()
        {

            self::initMigratingFlag("corporation_marketorders");
            if (self::statusMigratingFlag("corporation_marketorders")) {
                return true;
            }

            $q = self::$_oldDatabase->prepare("SELECT COUNT(*) AS nb_rows FROM corporation_marketorders");
            $q->execute();
            $nb_rows = $q->fetch(PDO::FETCH_ASSOC)['nb_rows'];
            $q->closeCursor();

            $migrated_line = 0;
            for ($curr_row = 0; $curr_row <= $nb_rows; $curr_row += self::$_maxRows) {
                $rows = CorporationUpgrade::fetchOldMarketOrder(self::$_oldDatabase, $curr_row, self::$_maxRows);
                foreach ($rows as $row) {
                    if (CorporationUpgrade::insertNewMarketOrder(self::$_newDatabase, $row)) {
                        Helper::displayProgress(++$migrated_line, $nb_rows);
                    } else {
                        return false;
                    }
                }
            }

            self::updateMigratingFlag("corporation_marketorders");

            return true;
        }

        // Message migration

        private static function upgradeCharacterJournal()
        {

            self::initMigratingFlag("character_walletjournal");
            if (self::statusMigratingFlag("character_walletjournal")) {
                return true;
            }

            $q = self::$_oldDatabase->prepare("SELECT COUNT(*) AS nb_rows FROM character_walletjournal");
            $q->execute();
            $nb_rows = $q->fetch(PDO::FETCH_ASSOC)['nb_rows'];
            $q->closeCursor();

            $migrated_line = 0;
            for ($curr_row = 0; $curr_row <= $nb_rows; $curr_row += self::$_maxRows) {
                $rows = CharacterUpgrade::fetchOldJournal(self::$_oldDatabase, $curr_row, self::$_maxRows);
                foreach ($rows as $row) {
                    if (CharacterUpgrade::insertNewJournal(self::$_newDatabase, $row)) {
                        Helper::displayProgress(++$migrated_line, $nb_rows);
                    } else {
                        return false;
                    }
                }
            }

            self::updateMigratingFlag("character_walletjournal");

            return true;
        }

        private static function upgradeCorporationJournal()
        {

            self::initMigratingFlag("corporation_walletjournal");
            if (self::statusMigratingFlag("corporation_walletjournal")) {
                return true;
            }

            $q = self::$_oldDatabase->prepare("SELECT COUNT(*) AS nb_rows FROM corporation_walletjournal");
            $q->execute();
            $nb_rows = $q->fetch(PDO::FETCH_ASSOC)['nb_rows'];
            $q->closeCursor();

            $migrated_line = 0;
            for ($curr_row = 0; $curr_row <= $nb_rows; $curr_row += self::$_maxRows) {
                $rows = CorporationUpgrade::fetchOldJournal(self::$_oldDatabase, $curr_row, self::$_maxRows);
                foreach ($rows as $row) {
                    if (CorporationUpgrade::insertNewJournal(self::$_newDatabase, $row)) {
                        Helper::displayProgress(++$migrated_line, $nb_rows);
                    } else {
                        return false;
                    }
                }
            }

            self::updateMigratingFlag("corporation_walletjournal");

            return true;
        }

        private static function upgradeCharacterTransaction()
        {

            self::initMigratingFlag("character_wallettransactions");
            if (self::statusMigratingFlag("character_wallettransactions")) {
                return true;
            }

            $q = self::$_oldDatabase->prepare("SELECT COUNT(*) AS nb_rows FROM character_wallettransactions");
            $q->execute();
            $nb_rows = $q->fetch(PDO::FETCH_ASSOC)['nb_rows'];
            $q->closeCursor();

            $migrated_line = 0;
            for ($curr_row = 0; $curr_row <= $nb_rows; $curr_row += self::$_maxRows) {
                $rows = CharacterUpgrade::fetchOldTransaction(self::$_oldDatabase, $curr_row, self::$_maxRows);
                foreach ($rows as $row) {
                    if (CharacterUpgrade::insertNewTransaction(self::$_newDatabase, $row)) {
                        Helper::displayProgress(++$migrated_line, $nb_rows);
                    } else {
                        return false;
                    }
                }
            }

            self::updateMigratingFlag("character_wallettransactions");

            return true;
        }

        private static function upgradeCorporationTransaction()
        {

            self::initMigratingFlag("corporation_wallettransactions");
            if (self::statusMigratingFlag("corporation_wallettransactions")) {
                return true;
            }

            $q = self::$_oldDatabase->prepare("SELECT COUNT(*) AS nb_rows FROM corporation_wallettransactions");
            $q->execute();
            $nb_rows = $q->fetch(PDO::FETCH_ASSOC)['nb_rows'];
            $q->closeCursor();

            $migrated_line = 0;
            for ($curr_row = 0; $curr_row <= $nb_rows; $curr_row += self::$_maxRows) {
                $rows = CorporationUpgrade::fetchOldTransaction(self::$_oldDatabase, $curr_row, self::$_maxRows);
                foreach ($rows as $row) {
                    if (CorporationUpgrade::insertNewTransaction(self::$_newDatabase, $row)) {
                        Helper::displayProgress(++$migrated_line, $nb_rows);
                    } else {
                        return false;
                    }
                }
            }

            self::updateMigratingFlag("corporation_wallettransactions");

            return true;
        }

        private static function upgradeMessageData()
        {

            // Kills Upgrade
            echo "\033[0;34m migrating character_killmails to character_kill_mails...\033[0m        ";
            if (self::upgradeCharacterKill()) {
                echo "\033[0;32m [SUCCESS]\033[0m\n";
            } else {
                echo "\033[0;31m [FAILED]\033[0m\n";
            }

            echo "\033[0;34m migrating character_killmail_items to kill_mail_items...\033[0m        ";
            if (self::upgradeCharacterKillItem()) {
                echo "\033[0;32m [SUCCESS]\033[0m\n";
            } else {
                echo "\033[0;31m [FAILED]\033[0m\n";
            }

            echo "\033[0;34m migrating character_killmail_detail to kill_mail_details...\033[0m        ";
            if (self::upgradeCharacterKillDetail()) {
                echo "\033[0;32m [SUCCESS]\033[0m\n";
            } else {
                echo "\033[0;31m [FAILED]\033[0m\n";
            }

            echo "\033[0;34m migrating character_killmail_attackers to kill_mail_attackers...\033[0m        ";
            if (self::upgradeCharacterKillAttacker()) {
                echo "\033[0;32m [SUCCESS]\033[0m\n";
            } else {
                echo "\033[0;31m [FAILED]\033[0m\n";
            }

            echo "\033[0;34m migrating corporation_killmails to corporation_kill_mails...\033[0m        ";
            if (self::upgradeCorporationKill()) {
                echo "\033[0;32m [SUCCESS]\033[0m\n";
            } else {
                echo "\033[0;31m [FAILED]\033[0m\n";
            }

            echo "\033[0;34m migrating corporation_killmail_items to kill_mail_items...\033[0m        ";
            if (self::upgradeCorporationKillItem()) {
                echo "\033[0;32m [SUCCESS]\033[0m\n";
            } else {
                echo "\033[0;31m [FAILED]\033[0m\n";
            }

            echo "\033[0;34m migrating corporation_killmail_detail to kill_mail_details...\033[0m        ";
            if (self::upgradeCorporationKillDetail()) {
                echo "\033[0;32m [SUCCESS]\033[0m\n";
            } else {
                echo "\033[0;31m [FAILED]\033[0m\n";
            }

            echo "\033[0;34m migrating corporation_killmail_attackers to kill_mail_attackers...\033[0m        ";
            if (self::upgradeCorporationKillAttacker()) {
                echo "\033[0;32m [SUCCESS]\033[0m\n";
            } else {
                echo "\033[0;31m [FAILED]\033[0m\n";
            }

            // Mails Upgrade
            echo "\033[0;34m migrating character_mailmessages to character_mail_messages...\033[0m        ";
            if (self::upgradeCharacterMessageHeader()) {
                echo "\033[0;32m [SUCCESS]\033[0m\n";
            } else {
                echo "\033[0;31m [FAILED]\033[0m\n";
            }

            echo "\033[0;34m migrating character_mailbodies to character_mail_message_bodies...\033[0m        ";
            if (self::upgradeCharacterMessageBody()) {
                echo "\033[0;32m [SUCCESS]\033[0m\n";
            } else {
                echo "\033[0;31m [FAILED]\033[0m\n";
            }

            echo "\033[0;34m migrating character_mailinglists to character_mailing_lists...\033[0m        ";
            if (self::upgradeCharacterMailingList()) {
                echo "\033[0;32m [SUCCESS]\033[0m\n";
            } else {
                echo "\033[0;31m [FAILED]\033[0m\n";
            }

            echo "\033[0;34m migrating character_mailinglists to character_mailing_list_infos...\033[0m        ";
            if (self::upgradeCharacterMailingInfo()) {
                echo "\033[0;32m [SUCCESS]\033[0m\n";
            } else {
                echo "\033[0;31m [FAILED]\033[0m\n";
            }

            echo "\033[0;34m migrating character_notifications to character_notifications...\033[0m        ";
            if (self::upgradeCharacterNotification()) {
                echo "\033[0;32m [SUCCESS]\033[0m\n";
            } else {
                echo "\033[0;31m [FAILED]\033[0m\n";
            }

            echo "\033[0;34m migrating character_notification_texts to character_notifications_texts...\033[0m        ";
            if (self::upgradeCharacterNotificationContent()) {
                echo "\033[0;32m [SUCCESS]\033[0m\n";
            } else {
                echo "\033[0;31m [FAILED]\033[0m\n";
            }
        }

        private static function upgradeCharacterKill()
        {

            self::initMigratingFlag("character_killmails");
            if (self::statusMigratingFlag("character_killmails")) {
                return true;
            }

            $q = self::$_oldDatabase->prepare("SELECT COUNT(*) AS nb_rows FROM character_killmails");
            $q->execute();
            $nb_rows = $q->fetch(PDO::FETCH_ASSOC)['nb_rows'];
            $q->closeCursor();

            $migrated_line = 0;
            for ($curr_row = 0; $curr_row <= $nb_rows; $curr_row += self::$_maxRows) {
                $rows = CharacterUpgrade::fetchOldKill(self::$_oldDatabase, $curr_row, self::$_maxRows);
                foreach ($rows as $row) {
                    if (CharacterUpgrade::insertNewKill(self::$_newDatabase, $row)) {
                        Helper::displayProgress(++$migrated_line, $nb_rows);
                    } else {
                        return false;
                    }
                }
            }

            self::updateMigratingFlag("character_killmails");

            return true;
        }

        private static function upgradeCharacterKillItem()
        {

            self::initMigratingFlag("character_killmail_items");
            if (self::statusMigratingFlag("character_killmail_items")) {
                return true;
            }

            $q = self::$_oldDatabase->prepare("SELECT COUNT(*) AS nb_rows FROM character_killmail_items");
            $q->execute();
            $nb_rows = $q->fetch(PDO::FETCH_ASSOC)['nb_rows'];
            $q->closeCursor();

            $migrated_line = 0;
            for ($curr_row = 0; $curr_row <= $nb_rows; $curr_row += self::$_maxRows) {
                $rows = CharacterUpgrade::fetchOldKillItem(self::$_oldDatabase, $curr_row, self::$_maxRows);
                foreach ($rows as $row) {
                    if (CharacterUpgrade::insertNewKillItem(self::$_newDatabase, $row)) {
                        Helper::displayProgress(++$migrated_line, $nb_rows);
                    } else {
                        return false;
                    }
                }
            }

            self::updateMigratingFlag("character_killmail_items");

            return true;
        }

        private static function upgradeCharacterKillDetail()
        {

            self::initMigratingFlag("character_killmail_detail");
            if (self::statusMigratingFlag("character_killmail_detail")) {
                return true;
            }

            $q = self::$_oldDatabase->prepare("SELECT COUNT(*) AS nb_rows FROM character_killmail_detail");
            $q->execute();
            $nb_rows = $q->fetch(PDO::FETCH_ASSOC)['nb_rows'];
            $q->closeCursor();

            $migrated_line = 0;
            for ($curr_row = 0; $curr_row <= $nb_rows; $curr_row += self::$_maxRows) {
                $rows = CharacterUpgrade::fetchOldKillDetail(self::$_oldDatabase, $curr_row, self::$_maxRows);
                foreach ($rows as $row) {
                    if (CharacterUpgrade::insertNewKillDetail(self::$_newDatabase, $row)) {
                        Helper::displayProgress(++$migrated_line, $nb_rows);
                    } else {
                        return false;
                    }
                }
            }

            self::updateMigratingFlag("character_killmail_detail");

            return true;
        }

        private static function upgradeCharacterKillAttacker()
        {

            self::initMigratingFlag("character_killmail_attackers");
            if (self::statusMigratingFlag("character_killmail_attackers")) {
                return true;
            }

            $q = self::$_oldDatabase->prepare("SELECT COUNT(*) AS nb_rows FROM character_killmail_attackers");
            $q->execute();
            $nb_rows = $q->fetch(PDO::FETCH_ASSOC)['nb_rows'];
            $q->closeCursor();

            $migrated_line = 0;
            for ($curr_row = 0; $curr_row <= $nb_rows; $curr_row += self::$_maxRows) {
                $rows = CharacterUpgrade::fetchOldKillAttacker(self::$_oldDatabase, $curr_row, self::$_maxRows);
                foreach ($rows as $row) {
                    if (CharacterUpgrade::insertNewKillAttacker(self::$_newDatabase, $row)) {
                        Helper::displayProgress(++$migrated_line, $nb_rows);
                    } else {
                        return false;
                    }
                }
            }

            self::updateMigratingFlag("character_killmail_attackers");

            return true;
        }

        private static function upgradeCorporationKill()
        {

            self::initMigratingFlag("corporation_killmails");
            if (self::statusMigratingFlag("corporation_killmails")) {
                return true;
            }

            $q = self::$_oldDatabase->prepare("SELECT COUNT(*) AS nb_rows FROM corporation_killmails");
            $q->execute();
            $nb_rows = $q->fetch(PDO::FETCH_ASSOC)['nb_rows'];
            $q->closeCursor();

            $migrated_line = 0;
            for ($curr_row = 0; $curr_row <= $nb_rows; $curr_row += self::$_maxRows) {
                $rows = CorporationUpgrade::fetchOldKill(self::$_oldDatabase, $curr_row, self::$_maxRows);
                foreach ($rows as $row) {
                    if (CorporationUpgrade::insertNewKill(self::$_newDatabase, $row)) {
                        Helper::displayProgress(++$migrated_line, $nb_rows);
                    } else {
                        return false;
                    }
                }
            }

            self::updateMigratingFlag("corporation_killmails");

            return true;
        }

        private static function upgradeCorporationKillItem()
        {

            self::initMigratingFlag("corporation_killmail_items");
            if (self::statusMigratingFlag("corporation_killmail_items")) {
                return true;
            }

            $q = self::$_oldDatabase->prepare("SELECT COUNT(*) AS nb_rows FROM corporation_killmail_items");
            $q->execute();
            $nb_rows = $q->fetch(PDO::FETCH_ASSOC)['nb_rows'];
            $q->closeCursor();

            $migrated_line = 0;
            for ($curr_row = 0; $curr_row <= $nb_rows; $curr_row += self::$_maxRows) {
                $rows = CharacterUpgrade::fetchOldKillItem(self::$_oldDatabase, $curr_row, self::$_maxRows);
                foreach ($rows as $row) {
                    if (CharacterUpgrade::insertNewKillItem(self::$_newDatabase, $row)) {
                        Helper::displayProgress(++$migrated_line, $nb_rows);
                    } else {
                        return false;
                    }
                }
            }

            self::updateMigratingFlag("corporation_killmail_items");

            return true;
        }

        private static function upgradeCorporationKillDetail()
        {

            self::initMigratingFlag("corporation_killmail_detail");
            if (self::statusMigratingFlag("corporation_killmail_detail")) {
                return true;
            }

            $q = self::$_oldDatabase->prepare("SELECT COUNT(*) AS nb_rows FROM corporation_killmail_detail");
            $q->execute();
            $nb_rows = $q->fetch(PDO::FETCH_ASSOC)['nb_rows'];
            $q->closeCursor();

            $migrated_line = 0;
            for ($curr_row = 0; $curr_row <= $nb_rows; $curr_row += self::$_maxRows) {
                $rows = CharacterUpgrade::fetchOldKillDetail(self::$_oldDatabase, $curr_row, self::$_maxRows);
                foreach ($rows as $row) {
                    if (CharacterUpgrade::insertNewKillDetail(self::$_newDatabase, $row)) {
                        Helper::displayProgress(++$migrated_line, $nb_rows);
                    } else {
                        return false;
                    }
                }
            }

            self::updateMigratingFlag("corporation_killmail_detail");

            return true;
        }

        private static function upgradeCorporationKillAttacker()
        {

            self::initMigratingFlag("corporation_killmail_attackers");
            if (self::statusMigratingFlag("corporation_killmail_attackers")) {
                return true;
            }

            $q = self::$_oldDatabase->prepare("SELECT COUNT(*) AS nb_rows FROM corporation_killmail_attackers");
            $q->execute();
            $nb_rows = $q->fetch(PDO::FETCH_ASSOC)['nb_rows'];
            $q->closeCursor();

            $migrated_line = 0;
            for ($curr_row = 0; $curr_row <= $nb_rows; $curr_row += self::$_maxRows) {
                $rows = CharacterUpgrade::fetchOldKillAttacker(self::$_oldDatabase, $curr_row, self::$_maxRows);
                foreach ($rows as $row) {
                    if (CharacterUpgrade::insertNewKillAttacker(self::$_newDatabase, $row)) {
                        Helper::displayProgress(++$migrated_line, $nb_rows);
                    } else {
                        return false;
                    }
                }
            }

            self::updateMigratingFlag("corporation_killmail_attackers");

            return true;
        }

        private static function upgradeCharacterMessageHeader()
        {

            self::initMigratingFlag("character_mailmessages");
            if (self::statusMigratingFlag("character_mailmessages")) {
                return true;
            }

            $q = self::$_oldDatabase->prepare("SELECT COUNT(*) AS nb_rows FROM character_mailmessages");
            $q->execute();
            $nb_rows = $q->fetch(PDO::FETCH_ASSOC)['nb_rows'];
            $q->closeCursor();

            $migrated_line = 0;
            for ($curr_row = 0; $curr_row <= $nb_rows; $curr_row += self::$_maxRows) {
                $rows = CharacterUpgrade::fetchOldMailHeader(self::$_oldDatabase, $curr_row, self::$_maxRows);
                foreach ($rows as $row) {
                    if (CharacterUpgrade::insertNewMailHeader(self::$_newDatabase, $row)) {
                        Helper::displayProgress(++$migrated_line, $nb_rows);
                    } else {
                        return false;
                    }
                }
            }

            self::updateMigratingFlag("character_mailmessages");

            return true;
        }

        private static function upgradeCharacterMessageBody()
        {

            self::initMigratingFlag("character_mailbodies");
            if (self::statusMigratingFlag("character_mailbodies")) {
                return true;
            }

            $q = self::$_oldDatabase->prepare("SELECT COUNT(*) AS nb_rows FROM character_mailbodies");
            $q->execute();
            $nb_rows = $q->fetch(PDO::FETCH_ASSOC)['nb_rows'];
            $q->closeCursor();

            $migrated_line = 0;
            for ($curr_row = 0; $curr_row <= $nb_rows; $curr_row += self::$_maxRows) {
                $rows = CharacterUpgrade::fetchOldMailBody(self::$_oldDatabase, $curr_row, self::$_maxRows);
                foreach ($rows as $row) {
                    if (CharacterUpgrade::insertNewMailBody(self::$_newDatabase, $row)) {
                        Helper::displayProgress(++$migrated_line, $nb_rows);
                    } else {
                        return false;
                    }
                }
            }

            self::updateMigratingFlag("character_mailbodies");

            return true;
        }

        // User migration

        private static function upgradeCharacterMailingList()
        {

            self::initMigratingFlag("character_mailinglists");
            if (self::statusMigratingFlag("character_mailinglists")) {
                return true;
            }

            $q = self::$_oldDatabase->prepare("SELECT COUNT(*) AS nb_rows FROM character_mailinglists");
            $q->execute();
            $nb_rows = $q->fetch(PDO::FETCH_ASSOC)['nb_rows'];
            $q->closeCursor();

            $migrated_line = 0;
            for ($curr_row = 0; $curr_row <= $nb_rows; $curr_row += self::$_maxRows) {
                $rows = CharacterUpgrade::fetchOldMailingList(self::$_oldDatabase, $curr_row, self::$_maxRows);
                foreach ($rows as $row) {
                    if (CharacterUpgrade::insertNewMailingList(self::$_newDatabase, $row)) {
                        Helper::displayProgress(++$migrated_line, $nb_rows);
                    } else {
                        return false;
                    }
                }
            }

            self::updateMigratingFlag("character_mailinglists");

            return true;
        }

        private static function upgradeCharacterMailingInfo()
        {

            self::initMigratingFlag("character_mailinglists2");
            if (self::statusMigratingFlag("character_mailinglists2")) {
                return true;
            }

            $q = self::$_oldDatabase->prepare("SELECT COUNT(*) AS nb_rows FROM character_mailinglists");
            $q->execute();
            $nb_rows = $q->fetch(PDO::FETCH_ASSOC)['nb_rows'];
            $q->closeCursor();

            $migrated_line = 0;
            for ($curr_row = 0; $curr_row <= $nb_rows; $curr_row += self::$_maxRows) {
                $rows = CharacterUpgrade::fetchOldMailingInfo(self::$_oldDatabase, $curr_row, self::$_maxRows);
                foreach ($rows as $row) {
                    if (CharacterUpgrade::insertNewMailingInfo(self::$_newDatabase, $row)) {
                        Helper::displayProgress(++$migrated_line, $nb_rows);
                    } else {
                        return false;
                    }
                }
            }

            self::updateMigratingFlag("character_mailinglists2");

            return true;
        }

        private static function upgradeCharacterNotification()
        {

            self::initMigratingFlag("character_notifications");
            if (self::statusMigratingFlag("character_notifications")) {
                return true;
            }

            $q = self::$_oldDatabase->prepare("SELECT COUNT(*) AS nb_rows FROM character_notifications");
            $q->execute();
            $nb_rows = $q->fetch(PDO::FETCH_ASSOC)['nb_rows'];
            $q->closeCursor();

            $migrated_line = 0;
            for ($curr_row = 0; $curr_row <= $nb_rows; $curr_row += self::$_maxRows) {
                $rows = CharacterUpgrade::fetchOldNotification(self::$_oldDatabase, $curr_row, self::$_maxRows);
                foreach ($rows as $row) {
                    if (CharacterUpgrade::insertNewNotification(self::$_newDatabase, $row)) {
                        Helper::displayProgress(++$migrated_line, $nb_rows);
                    } else {
                        return false;
                    }
                }
            }

            self::updateMigratingFlag("character_notifications");

            return true;
        }

        // Upgrade script tools

        private static function upgradeCharacterNotificationContent()
        {

            self::initMigratingFlag("character_notification_texts");
            if (self::statusMigratingFlag("character_notification_texts")) {
                return true;
            }

            $q = self::$_oldDatabase->prepare("SELECT COUNT(*) AS nb_rows FROM character_notification_texts");
            $q->execute();
            $nb_rows = $q->fetch(PDO::FETCH_ASSOC)['nb_rows'];
            $q->closeCursor();

            $migrated_line = 0;
            for ($curr_row = 0; $curr_row <= $nb_rows; $curr_row += self::$_maxRows) {
                $rows = CharacterUpgrade::fetchOldNotificationContent(self::$_oldDatabase, $curr_row, self::$_maxRows);
                foreach ($rows as $row) {
                    if (CharacterUpgrade::insertNewNotificationContent(self::$_newDatabase, $row)) {
                        Helper::displayProgress(++$migrated_line, $nb_rows);
                    } else {
                        return false;
                    }
                }
            }

            self::updateMigratingFlag("character_notification_texts");

            return true;
        }

        private static function upgradeUserData()
        {

            // User Upgrade
            echo "\033[0;34m migrating seat_users to users...\033[0m        ";
            if (self::upgradeUser()) {
                echo "\033[0;32m [SUCCESS]\033[0m\n";
            } else {
                echo "\033[0;31m [FAILED]\033[0m\n";
            }

            // Api Key Upgrade
            echo "\033[0;34m migrating seat_keys to eve_api_keys...\033[0m        ";
            if (self::upgradeKey()) {
                echo "\033[0;32m [SUCCESS]\033[0m\n";
            } else {
                echo "\033[0;31m [FAILED]\033[0m\n";
            }
        }

        private static function upgradeUser()
        {

            self::initMigratingFlag("seat_users");
            if (self::statusMigratingFlag("seat_users")) {
                return true;
            }

            $q = self::$_oldDatabase->prepare("SELECT COUNT(*) AS nb_rows FROM seat_users");
            $q->execute();
            $nb_rows = $q->fetch(PDO::FETCH_ASSOC)['nb_rows'];
            $q->closeCursor();

            $migrated_line = 0;
            for ($curr_row = 0; $curr_row <= $nb_rows; $curr_row += self::$_maxRows) {
                $rows = SeatUpgrade::fetchOldUser(self::$_oldDatabase, $curr_row, self::$_maxRows);
                foreach ($rows as $row) {
                    if (SeatUpgrade::insertNewUser(self::$_newDatabase, $row)) {
                        Helper::displayProgress(++$migrated_line, $nb_rows);
                    } else {
                        return false;
                    }
                }
            }

            self::updateMigratingFlag("seat_users");

            return true;
        }

        private static function upgradeKey()
        {

            self::initMigratingFlag("seat_keys");
            if (self::statusMigratingFlag("seat_keys")) {
                return true;
            }

            $q = self::$_oldDatabase->prepare("SELECT COUNT(*) AS nb_rows FROM seat_keys");
            $q->execute();
            $nb_rows = $q->fetch(PDO::FETCH_ASSOC)['nb_rows'];
            $q->closeCursor();

            $migrated_line = 0;
            for ($curr_row = 0; $curr_row <= $nb_rows; $curr_row += self::$_maxRows) {
                $rows = SeatUpgrade::fetchOldKey(self::$_oldDatabase, $curr_row, self::$_maxRows);
                foreach ($rows as $row) {
                    if (SeatUpgrade::insertNewKey(self::$_newDatabase, $row)) {
                        Helper::displayProgress(++$migrated_line, $nb_rows);
                    } else {
                        return false;
                    }
                }
            }

            self::updateMigratingFlag("seat_keys");

            return true;
        }
    }

    class Helper
    {

        public static function fetchOld(PDO $db, $start, $limit, $sql)
        {

            $result = [];
            $q = $db->prepare($sql);
            $q->bindParam(':start', $start, PDO::PARAM_INT);
            $q->bindValue(':nb_rows', $limit, PDO::PARAM_INT);
            $q->execute();

            while ($row = $q->fetch(PDO::FETCH_ASSOC)) {
                $result[] = $row;
            }

            $q->closeCursor();

            return $result;
        }

        public static function displayProgress($accomplished, $total)
        {

            $percent = round((float)$accomplished / (float)$total, 2) * 100;
            echo "\033[7D\033[K"; // removing seven previous characters
            echo str_pad($percent, 5, ' ', STR_PAD_LEFT) . " %"; // insert the new value
        }
    }

    class SeatUpgrade
    {

        public static function fetchOldUser(PDO $db, $start, $limit)
        {

            $sql = "SELECT id, username, email, `password`, activated, last_login, last_login_source, remember_token, " .
                "created_at, updated_at FROM seat_users WHERE id <> 1 LIMIT :start, :nb_rows";

            return Helper::fetchOld($db, $start, $limit, $sql);
        }

        public static function insertNewUser(PDO $db, $row)
        {

            $sql = "INSERT IGNORE INTO users (id, `name`, email, `password`, active, last_login, last_login_source, " .
                "remember_token, created_at, updated_at) " .
                "VALUES (:id, :name, :mail, :password, :active, :lastdt, :source, :token, :created, :updated)";

            $q = $db->prepare($sql);
            $q->bindParam(':id', $row['id'], PDO::PARAM_INT);
            $q->bindParam(':name', $row['username'], PDO::PARAM_STR);
            $q->bindParam(':mail', $row['email'], PDO::PARAM_STR);
            $q->bindParam(':password', $row['password'], PDO::PARAM_STR);
            $q->bindParam(':active', $row['activated']);
            $q->bindParam(':lastdt', $row['last_login']);
            $q->bindParam(':source', $row['last_login_source'], PDO::PARAM_STR);
            $q->bindParam(':token', $row['remember_token'], PDO::PARAM_STR);
            $q->bindParam(':created', $row['created_at']);
            $q->bindParam(':updated', $row['updated_at']);
            $result = $q->execute();
            $q->closeCursor();

            return $result;
        }

        public static function fetchOldKey(PDO $db, $start, $limit)
        {

            $sql = "SELECT user_id, keyID, vCode, created_at, updated_at " .
                "FROM seat_keys LIMIT :start, :nb_rows";

            return Helper::fetchOld($db, $start, $limit, $sql);
        }

        public static function insertNewKey(PDO $db, $row)
        {

            $sql = "INSERT IGNORE INTO eve_api_keys (key_id, v_code, user_id, created_at, updated_at) " .
                "VALUES (:keyid, :vcode, :userid, :created, :updated)";

            $q = $db->prepare($sql);
            $q->bindParam(':keyid', $row['keyID'], PDO::PARAM_INT);
            $q->bindParam(':vcode', $row['vCode'], PDO::PARAM_STR);
            $q->bindParam(':userid', $row['user_id'], PDO::PARAM_INT);
            $q->bindParam(':created', $row['created_at']);
            $q->bindParam(':updated', $row['updated_at']);
            $result = $q->execute();
            $q->closeCursor();

            return $result;
        }
    }

    class CharacterUpgrade
    {

        public static function fetchOldMarketOrder(PDO $db, $start, $limit)
        {

            $sql = "SELECT `id`, `orderID`, `charID`, `stationID`, `volEntered`, `volRemaining`, `minVolume`, " .
                "`orderState`, `typeID`, `range`, `duration`, `escrow`, `price`, `bid`, `issued`, `created_at`, " .
                "`updated_at` " .
                "FROM character_marketorders " .
                "LIMIT :start, :nb_rows";

            return Helper::fetchOld($db, $start, $limit, $sql);
        }

        public static function insertNewMarketOrder(PDO $db, $row)
        {

            $sql = "INSERT IGNORE INTO character_market_orders (`id`, `orderID`, `charID`, `stationID`, " .
                "`volEntered`, `volRemaining`, `minVolume`, `orderState`, `typeID`, `range`, `accountKey`, `duration`, " .
                "`escrow`, `price`, `bid`, `issued`, `created_at`, `updated_at`) " .
                "VALUES (:id, :orderID, :charID, :stationID, :volEntered, :volRemaining, :minVolume, " .
                ":orderState, :typeID, :range, :accountKey, :duration, :escrow, :price, :bid, :issued, " .
                ":created, :updated)";

            $q = $db->prepare($sql);

            $q->bindParam(':id', $row['id'], PDO::PARAM_INT);
            $q->bindParam(':orderID', $row['orderID'], PDO::PARAM_INT);
            $q->bindParam(':charID', $row['charID'], PDO::PARAM_INT);
            $q->bindParam(':stationID', $row['stationID'], PDO::PARAM_INT);
            $q->bindParam(':volEntered', $row['volEntered']);
            $q->bindParam(':volRemaining', $row['volRemaining']);
            $q->bindParam(':minVolume', $row['minVolume']);
            $q->bindParam(':orderState', $row['orderState']);
            $q->bindParam(':typeID', $row['typeID'], PDO::PARAM_INT);
            $q->bindParam(':range', $row['range']);
            $q->bindValue(':accountKey', 1000, PDO::PARAM_INT); // hardcode the accountKey for the character which is always 1000
            $q->bindParam(':duration', $row['duration']);
            $q->bindParam(':escrow', $row['escrow']);
            $q->bindParam(':price', $row['price']);
            $q->bindParam(':bid', $row['bid']);
            $q->bindParam(':issued', $row['issued']);
            $q->bindParam(':created', $row['created_at']);
            $q->bindParam(':updated', $row['updated_at']);
            $result = $q->execute();
            $q->closeCursor();

            return $result;
        }

        public static function fetchOldJournal(PDO $db, $start, $limit)
        {

            $sql = "SELECT `hash`, `characterID`, `refID`, `date`, `refTypeID`, `ownerName1`, `ownerID1`, " .
                "`ownerName2`, `ownerID2`, `argName1`, `argID1`, `amount`, `balance`, `reason`, " .
                "`taxReceiverID`, `taxAmount`, `owner1TypeID`, `owner2TypeID`, `created_at`, `updated_at` " .
                "FROM character_walletjournal " .
                "LIMIT :start, :nb_rows";

            return Helper::fetchOld($db, $start, $limit, $sql);
        }

        public static function insertNewJournal(PDO $db, $row)
        {

            $sql = "INSERT IGNORE INTO character_wallet_journals (`hash`, `characterID`, `refID`, `date`, " .
                "`refTypeID`, `ownerName1`, `ownerID1`, `ownerName2`, `ownerID2`, `argName1`, `argID1`, " .
                "`amount`, `balance`, `reason`, `taxReceiverID`, `taxAmount`, `owner1TypeID`, " .
                "`owner2TypeID`, `created_at`, `updated_at`) VALUES (:hash, :characterID, :refID, :date, " .
                ":refTypeID, :ownerName1, :ownerID1, :ownerName2, :ownerID2, :argName1, :argID1, :amount, " .
                ":balance, :reason, :taxReceiverID, :taxAmount, :owner1TypeID, :owner2TypeID, :created, " .
                ":updated)";

            $q = $db->prepare($sql);

            $q->bindParam(':hash', $row['hash'], PDO::PARAM_STR);
            $q->bindParam(':characterID', $row['characterID'], PDO::PARAM_INT);
            $q->bindParam(':refID', $row['refID'], PDO::PARAM_INT);
            $q->bindParam(':date', $row['date']);
            $q->bindParam(':refTypeID', $row['refTypeID'], PDO::PARAM_INT);
            $q->bindParam(':ownerName1', $row['ownerName1'], PDO::PARAM_STR);
            $q->bindParam(':ownerID1', $row['ownerID1'], PDO::PARAM_INT);
            $q->bindParam(':ownerName2', $row['ownerName2'], PDO::PARAM_STR);
            $q->bindParam(':ownerID2', $row['ownerID2'], PDO::PARAM_INT);
            $q->bindParam(':argName1', $row['argName1']);
            $q->bindParam(':argID1', $row['argID1']);
            $q->bindParam(':amount', $row['amount']);
            $q->bindParam(':balance', $row['balance']);
            $q->bindParam(':reason', $row['reason'], PDO::PARAM_STR);
            $q->bindParam(':taxReceiverID', $row['taxReceiverID'], PDO::PARAM_INT);
            $q->bindParam(':taxAmount', $row['taxAmount']);
            $q->bindParam(':owner1TypeID', $row['owner1TypeID'], PDO::PARAM_INT);
            $q->bindParam(':owner2TypeID', $row['owner2TypeID'], PDO::PARAM_INT);
            $q->bindParam(':created', $row['created_at'], PDO::PARAM_STR);
            $q->bindParam(':updated', $row['updated_at'], PDO::PARAM_STR);
            $result = $q->execute();
            $q->closeCursor();

            return $result;
        }

        public static function fetchOldTransaction(PDO $db, $start, $limit)
        {

            $sql = "SELECT `hash`, `characterID`, `transactionID`, `transactionDateTime`, `quantity`, " .
                "`typeName`, `typeID`, `price`, `clientID`, `clientName`, `stationID`, `stationName`, " .
                "`transactionType`, `transactionFor`, `journalTransactionID`, `clientTypeID`, " .
                "`created_at`, `updated_at` FROM character_wallettransactions LIMIT :start, :nb_rows";

            return Helper::fetchOld($db, $start, $limit, $sql);
        }

        public static function insertNewTransaction(PDO $db, $row)
        {

            $sql = "INSERT IGNORE INTO character_wallet_transactions (hash, characterID, transactionID, " .
                "transactionDateTime, quantity, typeName, typeID, price, clientID, clientName, " .
                "stationID, stationName, transactionType, transactionFor, journalTransactionID, " .
                "clientTypeID, created_at, updated_at) VALUES (:hash, :characterID, :transactionID, " .
                ":transactionDateTime, :quantity, :typeName, :typeID, :price, :clientID, :clientName, " .
                ":stationID, :stationName, :transactionType, :transactionFor, :journalTransactionID, " .
                ":clientTypeID, :created, :updated)";

            $q = $db->prepare($sql);
            $q->bindParam(':hash', $row['hash'], PDO::PARAM_STR);
            $q->bindParam(':characterID', $row['characterID'], PDO::PARAM_INT);
            $q->bindParam(':transactionID', $row['transactionID'], PDO::PARAM_INT);
            $q->bindParam(':transactionDateTime', $row['transactionDateTime']);
            $q->bindParam(':quantity', $row['quantity']);
            $q->bindParam(':typeName', $row['typeName'], PDO::PARAM_STR);
            $q->bindParam(':typeID', $row['typeID'], PDO::PARAM_INT);
            $q->bindParam(':price', $row['price']);
            $q->bindParam(':clientID', $row['clientID'], PDO::PARAM_INT);
            $q->bindParam(':clientName', $row['clientName'], PDO::PARAM_STR);
            $q->bindParam(':stationID', $row['stationID'], PDO::PARAM_INT);
            $q->bindParam(':stationName', $row['stationName'], PDO::PARAM_STR);
            $q->bindParam(':transactionType', $row['transactionType']);
            $q->bindParam(':transactionFor', $row['transactionFor']);
            $q->bindParam(':journalTransactionID', $row['journalTransactionID'], PDO::PARAM_INT);
            $q->bindParam(':clientTypeID', $row['clientTypeID'], PDO::PARAM_INT);
            $q->bindParam(':created', $row['created_at']);
            $q->bindParam(':updated', $row['updated_at']);
            $result = $q->execute();
            $q->closeCursor();

            return $result;
        }

        public static function fetchOldKill(PDO $db, $start, $limit)
        {

            $sql = "SELECT characterID, killID, created_at, updated_at " .
                "FROM character_killmails " .
                "LIMIT :start, :nb_rows";

            return Helper::fetchOld($db, $start, $limit, $sql);
        }

        public static function insertNewKill(PDO $db, $row)
        {

            $sql = "INSERT IGNORE INTO character_kill_mails (characterID, killID, created_at, updated_at) " .
                " VALUES (:characterID, :killID, :created, :updated)";

            $q = $db->prepare($sql);
            $q->bindParam(':characterID', $row['characterID'], PDO::PARAM_INT);
            $q->bindParam(':killID', $row['killID'], PDO::PARAM_INT);
            $q->bindParam(':created', $row['created_at']);
            $q->bindParam(':updated', $row['updated_at']);
            $result = $q->execute();
            $q->closeCursor();

            return $result;
        }

        public static function fetchOldKillAttacker(PDO $db, $start, $limit)
        {

            $sql = "SELECT killID, characterID, characterName, corporationID, corporationName, allianceID, " .
                "allianceName, factionID, factionName, securityStatus, damageDone, finalBlow, weaponTypeID, " .
                "shipTypeID, created_at, updated_at " .
                "FROM character_killmail_attackers " .
                "LIMIT :start, :nb_rows";

            return Helper::fetchOld($db, $start, $limit, $sql);
        }

        public static function insertNewKillAttacker(PDO $db, $row)
        {

            $sql = "INSERT IGNORE INTO kill_mail_attackers (killID, characterID, characterName, corporationID, " .
                "corporationName, allianceID, allianceName, factionID, factionName, securityStatus, damageDone, " .
                "finalBlow, weaponTypeID, shipTypeID, created_at, updated_at) " .
                "VALUES (:kid, :cid, :cname, :crpid, :crpname, :aid, :aname, :fid, :fname, :ss, :dd, :fb, :wtid, " .
                ":stid, :created, :updated)";

            $q = $db->prepare($sql);
            $q->bindParam(':kid', $row['killID']);
            $q->bindParam(':cid', $row['characterID']);
            $q->bindParam(':cname', $row['characterName']);
            $q->bindParam(':crpid', $row['corporationID']);
            $q->bindParam(':crpname', $row['corporationName']);
            $q->bindParam(':aid', $row['allianceID']);
            $q->bindParam(':aname', $row['allianceName']);
            $q->bindParam(':fid', $row['factionID']);
            $q->bindParam(':fname', $row['factionName']);
            $q->bindParam(':ss', $row['securityStatus']);
            $q->bindParam(':dd', $row['damageDone']);
            $q->bindParam(':fb', $row['finalBlow']);
            $q->bindParam(':wtid', $row['weaponTypeID']);
            $q->bindParam(':stid', $row['shipTypeID']);
            $q->bindParam(':created', $row['created_at']);
            $q->bindParam(':updated', $row['updated_at']);
            $result = $q->execute();
            $q->closeCursor();

            return $result;
        }

        public static function fetchOldKillDetail(PDO $db, $start, $limit)
        {

            $sql = "SELECT killID, solarSystemID, killTime, moonID, characterID, characterName, corporationID, " .
                "corporationName, allianceID, allianceName, factionID, factionName, damageTaken, shipTypeID, created_at, " .
                "updated_at FROM character_killmail_detail LIMIT :start, :nb_rows";

            return Helper::fetchOld($db, $start, $limit, $sql);
        }

        public static function insertNewKillDetail(PDO $db, $row)
        {

            $sql = "INSERT IGNORE INTO kill_mail_details (killID, solarSystemID, killTime, moonID, characterID, " .
                "characterName, corporationID, corporationName, allianceID, allianceName, factionID, factionName, " .
                "damageTaken, shipTypeID, created_at, updated_at) VALUES (:kid, :sid, :kt, :mid, :cid, :cname, " .
                ":crpid, :crpname, :aid, :aname, :fid, :fname, :dt, :stid, :created, :updated)";

            $q = $db->prepare($sql);
            $q->bindParam(':kid', $row['killID']);
            $q->bindParam(':sid', $row['solarSystemID']);
            $q->bindParam(':kt', $row['killTime']);
            $q->bindParam(':mid', $row['moonID']);
            $q->bindParam(':cid', $row['characterID']);
            $q->bindParam(':cname', $row['characterName']);
            $q->bindParam(':crpid', $row['corporationID']);
            $q->bindParam(':crpname', $row['corporationName']);
            $q->bindParam(':aid', $row['allianceID']);
            $q->bindParam(':aname', $row['allianceName']);
            $q->bindParam(':fid', $row['factionID']);
            $q->bindParam(':fname', $row['factionName']);
            $q->bindParam(':dt', $row['damageTaken']);
            $q->bindParam(':stid', $row['shipTypeID']);
            $q->bindParam(':created', $row['created_at']);
            $q->bindParam(':updated', $row['updated_at']);
            $result = $q->execute();
            $q->closeCursor();

            return $result;
        }

        public static function fetchOldKillItem(PDO $db, $start, $limit)
        {

            $sql = "SELECT killID, typeID, flag, qtyDropped, qtyDestroyed, singleton, created_at, " .
                "updated_at FROM character_killmail_items LIMIT :start, :nb_rows";

            return Helper::fetchOld($db, $start, $limit, $sql);
        }

        public static function insertNewKillItem(PDO $db, $row)
        {

            $sql = "INSERT IGNORE INTO kill_mail_items (killID, typeID, flag, qtyDropped, qtyDestroyed, singleton, " .
                "created_at, updated_at) VALUES (:kid, :tid, :f, :qtdrp, :qtdst, :s, :created, :updated)";

            $q = $db->prepare($sql);
            $q->bindParam(':kid', $row['killID']);
            $q->bindParam(':tid', $row['typeID']);
            $q->bindParam(':f', $row['flag']);
            $q->bindParam(':qtdrp', $row['qtyDropped']);
            $q->bindParam(':qtdst', $row['qtyDestroyed']);
            $q->bindParam(':s', $row['singleton']);
            $q->bindParam(':created', $row['created_at']);
            $q->bindParam(':updated', $row['updated_at']);
            $result = $q->execute();
            $q->closeCursor();

            return $result;
        }

        public static function fetchOldMailHeader(PDO $db, $start, $limit)
        {

            $sql = "SELECT id, characterID, messageID, senderID, senderName, sentDate, title, toCorpOrAllianceID, " .
                "toCharacterIDs, toListID, created_at, updated_at FROM character_mailmessages " .
                "LIMIT :start, :nb_rows";

            return Helper::fetchOld($db, $start, $limit, $sql);
        }

        public static function insertNewMailHeader(PDO $db, $row)
        {

            $sql = "INSERT IGNORE INTO character_mail_messages (id, characterID, messageID, senderID, senderName, " .
                "sentDate, title, toCorpOrAllianceID, toCharacterIDs, toListID, created_at, updated_at) " .
                "VALUES (:id, :characterID, :messageID, :sendID, :senderName, :sentDate, :title, " .
                ":toCorpOrAllianceID, :toCharacterIDs, :toListID, :created, :updated)";

            $q = $db->prepare($sql);
            $q->bindParam(':id', $row['characterID'], PDO::PARAM_INT);
            $q->bindParam(':characterID', $row['characterID'], PDO::PARAM_INT);
            $q->bindParam(':messageID', $row['messageID'], PDO::PARAM_INT);
            $q->bindParam(':sendID', $row['sendID'], PDO::PARAM_INT);
            $q->bindParam(':senderName', $row['senderName'], PDO::PARAM_STR);
            $q->bindParam(':sentDate', $row['sentDate']);
            $q->bindParam(':title', $row['title'], PDO::PARAM_STR);
            $q->bindParam(':toCorpOrAllianceID', $row['toCorpOrAllianceID'], PDO::PARAM_INT);
            $q->bindParam(':toCharacterIDs', $row['toCharacterIDs'], PDO::PARAM_INT);
            $q->bindParam(':toListID', $row['toListID'], PDO::PARAM_INT);
            $q->bindParam(':created', $row['created_at']);
            $q->bindParam(':updated', $row['updated_at']);
            $result = $q->execute();
            $q->closeCursor();

            return $result;
        }

        public static function fetchOldMailBody(PDO $db, $start, $limit)
        {

            $sql = "SELECT messageID, body, created_at, updated_at FROM character_mailbodies " .
                "LIMIT :start, :nb_rows";

            return Helper::fetchOld($db, $start, $limit, $sql);
        }

        public static function insertNewMailBody(PDO $db, $row)
        {

            $sql = "INSERT IGNORE INTO character_mail_message_bodies (messageID, body, created_at, updated_at) " .
                "VALUES (:messageID, :body, :created, :updated)";

            $q = $db->prepare($sql);
            $q->bindParam(':messageID', $row['messageID'], PDO::PARAM_INT);
            $q->bindParam(':body', $row['body'], PDO::PARAM_STR);
            $q->bindParam(':created', $row['created_at']);
            $q->bindParam(':updated', $row['updated_at']);
            $result = $q->execute();
            $q->closeCursor();

            return $result;
        }

        public static function fetchOldMailingList(PDO $db, $start, $limit)
        {

            $sql = "SELECT characterID, listID, created_at, updated_at FROM character_mailinglists " .
                "LIMIT :start, :nb_rows";

            return Helper::fetchOld($db, $start, $limit, $sql);
        }

        public static function insertNewMailingList(PDO $db, $row)
        {

            $sql = "INSERT IGNORE INTO character_mailing_lists (characterID, listID, created_at, updated_at) " .
                "VALUES (:characterID, :listID, :created, :updated)";

            $q = $db->prepare($sql);
            $q->bindParam(':characterID', $row['characterID'], PDO::PARAM_INT);
            $q->bindParam(':listID', $row['listID'], PDO::PARAM_INT);
            $q->bindParam(':created', $row['created_at']);
            $q->bindParam(':updated', $row['updated_at']);
            $result = $q->execute();
            $q->closeCursor();

            return $result;
        }

        public static function fetchOldMailingInfo(PDO $db, $start, $limit)
        {

            $sql = "SELECT listID, displayName, created_at, updated_at FROM character_mailinglists " .
                "LIMIT :start, :nb_rows";

            return Helper::fetchOld($db, $start, $limit, $sql);
        }

        public static function insertNewMailingInfo(PDO $db, $row)
        {

            $sql = "INSERT IGNORE INTO character_mailing_list_infos (listID, displayName, created_at, updated_at) " .
                "VALUES (:listID, :displayName, :created, :updated)";

            $q = $db->prepare($sql);
            $q->bindParam(':listID', $row['characterID'], PDO::PARAM_INT);
            $q->bindParam(':displayName', $row['displayName'], PDO::PARAM_STR);
            $q->bindParam(':created', $row['created_at']);
            $q->bindParam(':updated', $row['updated_at']);
            $result = $q->execute();
            $q->closeCursor();

            return $result;
        }

        public static function fetchOldNotification(PDO $db, $start, $limit)
        {

            $sql = "SELECT id, characterID, notificationID, typeID, senderID, senderName, sentDate, `read`, " .
                "created_at, updated_at FROM character_notifications " .
                "LIMIT :start, :nb_rows";

            return Helper::fetchOld($db, $start, $limit, $sql);
        }

        public static function insertNewNotification(PDO $db, $row)
        {

            $sql = "INSERT IGNORE INTO character_notifications (id, characterID, notificationID, typeID, senderID, " .
                "senderName, sentDate, `read`, created_at, updated_at) " .
                "VALUES (:id, :characterID, :notificationID, :typeID, :senderID, :senderName, :sentDate, :flag, " .
                ":created, :updated)";

            $q = $db->prepare($sql);
            $q->bindParam(':id', $row['id'], PDO::PARAM_INT);
            $q->bindParam(':characterID', $row['characterID'], PDO::PARAM_INT);
            $q->bindParam(':notificationID', $row['notificationID'], PDO::PARAM_INT);
            $q->bindParam(':typeID', $row['typeID'], PDO::PARAM_INT);
            $q->bindParam(':senderID', $row['senderID'], PDO::PARAM_INT);
            $q->bindParam(':senderName', $row['senderName'], PDO::PARAM_STR);
            $q->bindParam(':sentDate', $row['sentDate']);
            $q->bindParam(':flag', $row['read'], PDO::PARAM_BOOL);
            $q->bindParam(':created', $row['created_at']);
            $q->bindParam(':updated', $row['updated_at']);
            $result = $q->execute();
            $q->closeCursor();

            return $result;
        }

        public static function fetchOldNotificationContent(PDO $db, $start, $limit)
        {

            $sql = "SELECT notificationID, text, created_at, updated_at FROM character_notification_texts " .
                "LIMIT :start, :nb_rows";

            return Helper::fetchOld($db, $start, $limit, $sql);
        }

        public static function insertNewNotificationContent(PDO $db, $row)
        {

            $sql = "INSERT IGNORE INTO character_notifications_texts (notificationID, text, created_at, updated_at) " .
                "VALUES (:notificationID, :text, :created, :updated)";

            $q = $db->prepare($sql);
            $q->bindParam(':notificationID', $row['notificationID'], PDO::PARAM_INT);
            $q->bindParam(':text', $row['text'], PDO::PARAM_STR);
            $q->bindParam(':created', $row['created_at']);
            $q->bindParam(':updated', $row['updated_at']);
            $result = $q->execute();
            $q->closeCursor();

            return $result;
        }

    }

    class CorporationUpgrade
    {

        public static function fetchOldMarketOrder(PDO $db, $start, $limit)
        {

            $sql = "SELECT `orderID`, `corporationID`, `charID`, `stationID`, `volEntered`, `volRemaining`, " .
                "`minVolume`, `orderState`, `typeID`, `range`, `accountKey`, `duration`, `escrow`, `price`, " .
                "`bid`, `issued`, `created_at`, `updated_at` " .
                "FROM corporation_marketorders " .
                "LIMIT :start, :nb_rows";

            return Helper::fetchOld($db, $start, $limit, $sql);
        }

        public static function insertNewMarketOrder(PDO $db, $row)
        {

            $sql = "INSERT IGNORE INTO corporation_market_orders (`orderID`, `corporationID`, `charID`, `stationID`, " .
                "`volEntered`, `volRemaining`, `minVolume`, `orderState`, `typeID`, `range`, `accountKey`, " .
                "`duration`, `escrow`, `price`, `bid`, `issued`, `created_at`, `updated_at`) " .
                "VALUES (:orderID, :corporationID, :charID, :stationID, :volEntered, :volRemaining, " .
                ":minVolume, :orderState, :typeID, :range, :accountKey, :duration, :escrow, :price, :bid, " .
                ":issued, :created, :updated)";

            $q = $db->prepare($sql);
            $q->bindParam(':orderID', $row['orderID'], PDO::PARAM_INT);
            $q->bindParam(':corporationID', $row['corporationID'], PDO::PARAM_INT);
            $q->bindParam(':charID', $row['charID'], PDO::PARAM_INT);
            $q->bindParam(':stationID', $row['stationID'], PDO::PARAM_INT);
            $q->bindParam(':volEntered', $row['volEntered']);
            $q->bindParam(':volRemaining', $row['volRemaining']);
            $q->bindParam(':minVolume', $row['minVolume']);
            $q->bindParam(':orderState', $row['orderState']);
            $q->bindParam(':typeID', $row['typeID'], PDO::PARAM_INT);
            $q->bindParam(':range', $row['range']);
            $q->bindValue(':accountKey', 1000, PDO::PARAM_INT); // hardcode the accountKey for the character which is always 1000
            $q->bindParam(':duration', $row['duration']);
            $q->bindParam(':escrow', $row['escrow']);
            $q->bindParam(':price', $row['price']);
            $q->bindParam(':bid', $row['bid']);
            $q->bindParam(':issued', $row['issued']);
            $q->bindParam(':created', $row['created_at']);
            $q->bindParam(':updated', $row['updated_at']);
            $result = $q->execute();
            $q->closeCursor();

            return $result;
        }

        public static function fetchOldJournal(PDO $db, $start, $limit)
        {

            $sql = "SELECT `hash`, `corporationID`, `accountKey`, `refID`, `date`, `refTypeID`, `ownerName1`, " .
                "`ownerID1`, `ownerName2`, `ownerID2`, `argName1`, `argID1`, `amount`, `balance`, reason, " .
                "owner1TypeID, owner2TypeID, created_at, updated_at " .
                "FROM corporation_walletjournal " .
                "LIMIT :start, :nb_rows";

            return Helper::fetchOld($db, $start, $limit, $sql);
        }

        public static function insertNewJournal(PDO $db, $row)
        {

            $sql = "INSERT IGNORE INTO corporation_wallet_journals (`hash`, `corporationID`, `accountKey`, `refID`, " .
                "`date`, `refTypeID`, `ownerName1`, `ownerID1`, `ownerName2`, `ownerID2`, `argName1`, " .
                "`argID1`, `amount`, `balance`, `reason`, `owner1TypeID`, `owner2TypeID`, `created_at`, " .
                "`updated_at`) VALUES (:hash, :corporationID, :accountKey, :refID, :date, :refTypeID, " .
                ":ownerName1, :ownerID1, :ownerName2, :ownerID2, :argName1, :argID1, :amount, :balance, " .
                ":reason, :owner1TypeID, :owner2TypeID, :created, :updated)";

            $q = $db->prepare($sql);
            $q->bindParam(':hash', $row['hash'], PDO::PARAM_STR);
            $q->bindParam(':corporationID', $row['corporationID'], PDO::PARAM_INT);
            $q->bindParam(':accountKey', $row['accountKey'], PDO::PARAM_INT);
            $q->bindParam(':refID', $row['refID'], PDO::PARAM_INT);
            $q->bindParam(':date', $row['date']);
            $q->bindParam(':refTypeID', $row['refTypeID'], PDO::PARAM_INT);
            $q->bindParam(':ownerName1', $row['ownerName1'], PDO::PARAM_STR);
            $q->bindParam(':ownerID1', $row['ownerID1'], PDO::PARAM_INT);
            $q->bindParam(':ownerName2', $row['ownerName2'], PDO::PARAM_STR);
            $q->bindParam(':ownerID2', $row['ownerID2'], PDO::PARAM_INT);
            $q->bindParam(':argName1', $row['argName1']);
            $q->bindParam(':argID1', $row['argID1']);
            $q->bindParam(':amount', $row['amount']);
            $q->bindParam(':balance', $row['balance']);
            $q->bindParam(':reason', $row['reason'], PDO::PARAM_STR);
            $q->bindParam(':owner1TypeID', $row['owner1TypeID'], PDO::PARAM_INT);
            $q->bindParam(':owner2TypeID', $row['owner2TypeID'], PDO::PARAM_INT);
            $q->bindParam(':created', $row['created_at'], PDO::PARAM_STR);
            $q->bindParam(':updated', $row['updated_at'], PDO::PARAM_STR);
            $result = $q->execute();
            $q->closeCursor();

            return $result;
        }

        public static function fetchOldTransaction(PDO $db, $start, $limit)
        {

            $sql = "SELECT `hash`, corporationID, accountKey, transactionDateTime, transactionID, " .
                "quantity, typeName, typeID, price, clientID, clientName, stationID, stationName, " .
                "transactionType, transactionFor, journalTransactionID, clientTypeID, created_at, " .
                "updated_at FROM corporation_wallettransactions LIMIT :start, :nb_rows";

            return Helper::fetchOld($db, $start, $limit, $sql);
        }

        public static function insertNewTransaction(PDO $db, $row)
        {

            $sql = "INSERT IGNORE INTO corporation_wallet_transactions (`hash`, corporationID, accountKey, " .
                "transactionDateTime, transactionID, quantity, typeName, typeID, price, clientID, " .
                "clientName, stationID, stationName, transactionType, transactionFor, " .
                "journalTransactionID, clientTypeID, created_at, updated_at) VALUES (:hash, " .
                ":corporationID, :accountKey, :transactionDateTime, :transactionID, :quantity, " .
                ":typeName, :typeID, :price, :clientID, :clientName, :stationID, :stationName, " .
                ":transactionType, :transactionFor, :journalTransactionID, :clientTypeID, :created, " .
                ":updated)";

            $q = $db->prepare($sql);
            $q->bindParam(':hash', $row['hash'], PDO::PARAM_STR);
            $q->bindParam(':corporationID', $row['corporationID'], PDO::PARAM_INT);
            $q->bindParam(':accountKey', $row['accountKey'], PDO::PARAM_INT);
            $q->bindParam(':transactionID', $row['transactionID'], PDO::PARAM_INT);
            $q->bindParam(':transactionDateTime', $row['transactionDateTime']);
            $q->bindParam(':quantity', $row['quantity']);
            $q->bindParam(':typeName', $row['typeName'], PDO::PARAM_STR);
            $q->bindParam(':typeID', $row['typeID'], PDO::PARAM_INT);
            $q->bindParam(':price', $row['price']);
            $q->bindParam(':clientID', $row['clientID'], PDO::PARAM_INT);
            $q->bindParam(':clientName', $row['clientName'], PDO::PARAM_STR);
            $q->bindParam(':stationID', $row['stationID'], PDO::PARAM_INT);
            $q->bindParam(':stationName', $row['stationName'], PDO::PARAM_STR);
            $q->bindParam(':transactionType', $row['transactionType']);
            $q->bindParam(':transactionFor', $row['transactionFor']);
            $q->bindParam(':journalTransactionID', $row['journalTransactionID'], PDO::PARAM_INT);
            $q->bindParam(':clientTypeID', $row['clientTypeID'], PDO::PARAM_INT);
            $q->bindParam(':created', $row['created_at']);
            $q->bindParam(':updated', $row['updated_at']);
            $result = $q->execute();
            $q->closeCursor();

            return $result;
        }

        public static function fetchOldKill(PDO $db, $start, $limit)
        {

            $sql = "SELECT corporationID, killID, created_at, updated_at " .
                "FROM corporation_killmails " .
                "LIMIT :start, :nb_rows";

            return Helper::fetchOld($db, $start, $limit, $sql);
        }

        public static function insertNewKill(PDO $db, $row)
        {

            $sql = "INSERT IGNORE INTO corporation_kill_mails (corporationID, killID, created_at, updated_at) " .
                "VALUES (:corporationID, :killID, :created, :updated)";

            $q = $db->prepare($sql);
            $q->bindParam(':corporationID', $row['corporationID'], PDO::PARAM_INT);
            $q->bindParam(':killID', $row['killID'], PDO::PARAM_INT);
            $q->bindParam(':created', $row['created_at']);
            $q->bindParam(':updated', $row['updated_at']);
            $result = $q->execute();
            $q->closeCursor();

            return $result;
        }

        public static function fetchOldKillAttacker(PDO $db, $start, $limit)
        {

            $sql = "SELECT killID, characterID, characterName, corporationID, corporationName, allianceID, " .
                "allianceName, factionID, factionName, securityStatus, damageDone, finalBlow, weaponTypeID, " .
                "shipTypeID, created_at, updated_at " .
                "FROM corporation_killmail_attackers " .
                "LIMIT :start, :nb_rows";

            return Helper::fetchOld($db, $start, $limit, $sql);
        }

        public static function insertNewKillAttacker(PDO $db, $row)
        {

            $sql = "INSERT IGNORE INTO kill_mail_attackers (killID, characterID, characterName, corporationID, " .
                "corporationName, allianceID, allianceName, factionID, factionName, securityStatus, damageDone, " .
                "finalBlow, weaponTypeID, shipTypeID, created_at, updated_at) " .
                "VALUES (:kid, :cid, :cname, :crpid, :crpname, :aid, :aname, :fid, :fname, :ss, :dd, :fb, :wtid, " .
                ":stid, :created, :updated)";

            $q = $db->prepare($sql);
            $q->bindParam(':kid', $row['killID']);
            $q->bindParam(':cid', $row['characterID']);
            $q->bindParam(':cname', $row['characterName']);
            $q->bindParam(':crpid', $row['corporationID']);
            $q->bindParam(':crpname', $row['corporationName']);
            $q->bindParam(':aid', $row['allianceID']);
            $q->bindParam(':aname', $row['allianceName']);
            $q->bindParam(':fid', $row['factionID']);
            $q->bindParam(':fname', $row['factionName']);
            $q->bindParam(':ss', $row['securityStatus']);
            $q->bindParam(':dd', $row['damageDone']);
            $q->bindParam(':fb', $row['finalBlow']);
            $q->bindParam(':wtid', $row['weaponTypeID']);
            $q->bindParam(':stid', $row['shipTypeID']);
            $q->bindParam(':created', $row['created_at']);
            $q->bindParam(':updated', $row['updated_at']);
            $result = $q->execute();
            $q->closeCursor();

            return $result;
        }

        public static function fetchOldKillDetail(PDO $db, $start, $limit)
        {

            $sql = "SELECT killID, solarSystemID, killTime, moonID, characterID, characterName, corporationID, " .
                "corporationName, allianceID, allianceName, factionID, factionName, damageTaken, shipTypeID, created_at, " .
                "updated_at FROM corporation_killmail_detail LIMIT :start, :nb_rows";

            return Helper::fetchOld($db, $start, $limit, $sql);
        }

        public static function insertNewKillDetail(PDO $db, $row)
        {

            $sql = "INSERT IGNORE INTO kill_mail_details (killID, solarSystemID, killTime, moonID, characterID, " .
                "characterName, corporationID, corporationName, allianceID, allianceName, factionID, factionName, " .
                "damageTaken, shipTypeID, created_at, updated_at) VALUES (:kid, :sid, :kt, :mid, :cid, :cname, " .
                ":crpid, :crpname, :aid, :aname, :fid, :fname, :dt, :stid, :created, :updated)";

            $q = $db->prepare($sql);
            $q->bindParam(':kid', $row['killID']);
            $q->bindParam(':sid', $row['solarSystemID']);
            $q->bindParam(':kt', $row['killTime']);
            $q->bindParam(':mid', $row['moonID']);
            $q->bindParam(':cid', $row['characterID']);
            $q->bindParam(':cname', $row['characterName']);
            $q->bindParam(':crpid', $row['corporationID']);
            $q->bindParam(':crpname', $row['corporationName']);
            $q->bindParam(':aid', $row['allianceID']);
            $q->bindParam(':aname', $row['allianceName']);
            $q->bindParam(':fid', $row['factionID']);
            $q->bindParam(':fname', $row['factionName']);
            $q->bindParam(':dt', $row['damageTaken']);
            $q->bindParam(':stid', $row['shipTypeID']);
            $q->bindParam(':created', $row['created_at']);
            $q->bindParam(':updated', $row['updated_at']);
            $result = $q->execute();
            $q->closeCursor();

            return $result;
        }

        public static function fetchOldKillItem(PDO $db, $start, $limit)
        {

            $sql = "SELECT killID, typeID, flag, qtyDropped, qtyDestroyed, singleton, created_at, " .
                "updated_at FROM corporation_killmail_items LIMIT :start, :nb_rows";

            return Helper::fetchOld($db, $start, $limit, $sql);
        }

        public static function insertNewKillItem(PDO $db, $row)
        {

            $sql = "INSERT IGNORE INTO kill_mail_items (killID, typeID, flag, qtyDropped, qtyDestroyed, singleton, " .
                "created_at, updated_at) VALUES (:kid, :tid, :f, :qtdrp, :qtdst, :s, :created, :updated)";

            $q = $db->prepare($sql);
            $q->bindParam(':kid', $row['killID']);
            $q->bindParam(':tid', $row['typeID']);
            $q->bindParam(':f', $row['flag']);
            $q->bindParam(':qtdrp', $row['qtyDropped']);
            $q->bindParam(':qtdst', $row['qtyDestroyed']);
            $q->bindParam(':s', $row['singleton']);
            $q->bindParam(':created', $row['created_at']);
            $q->bindParam(':updated', $row['updated_at']);
            $result = $q->execute();
            $q->closeCursor();

            return $result;
        }
    }

    /**
     * AUTO START SCRIPT
     */

    $parameters = getopt('', ['ohost::', 'oport::', 'oname:', 'ouser:', 'opass::', 'nhost::', 'nport::', 'nname:', 'nuser:', 'npass::']);
    $parameters['ohost'] = (isset($parameters['ohost'])) ? $parameters['ohost'] : "localhost";
    $parameters['nhost'] = (isset($parameters['nhost'])) ? $parameters['nhost'] : "localhost";
    $parameters['oport'] = (isset($parameters['oport'])) ? $parameters['oport'] : "3306";
    $parameters['nport'] = (isset($parameters['nport'])) ? $parameters['nport'] : "3306";
    $parameters['opass'] = (isset($parameters['opass'])) ? $parameters['opass'] : "";
    $parameters['npass'] = (isset($parameters['npass'])) ? $parameters['npass'] : "";
    if (count($parameters) == 10) {
        $code = Script::main($parameters['ohost'], $parameters['oport'], $parameters['oname'], $parameters['ouser'],
            $parameters['opass'], $parameters['nhost'], $parameters['nport'], $parameters['nname'],
            $parameters['nuser'], $parameters['npass']);

        exit($code);
    }

    echo "\033[1;37m NAME\033[0m\r\n";
    echo "DistUpgrade - SeAT upgrader\r\n";
    echo "\r\n";
    echo "\033[1;37m SYNOPSIS\033[0m\r\n";
    echo "php distUpgrade.php [--ohost=] [--oport=] --oname= --ouser= [--opass=] [--nhost=] [--nport=] --nname= --nuser= [--npass=]\r\n";
    echo "\r\n";
    echo "\033[1;37m DESCRIPTION\033[0m\r\n";
    echo "This CLI script enable you to migrate data from your SeAT 0.14.1 instance to a new SeAT 1.0.0 instance\r\n";
    echo "\r\n";
    echo "\033[1;37m --ohost\033[0m\r\n";
    echo "This is the database hostname from your OLD SeAT\r\n";
    echo "\r\n";
    echo "\033[1;37m --oport\033[0m\r\n";
    echo "This is the database port from your OLD SeAT\r\n";
    echo "\r\n";
    echo "\033[1;37m --oname\033[0m\r\n";
    echo "This is the database name from your OLD SeAT\r\n";
    echo "\r\n";
    echo "\033[1;37m --ouser\033[0m\r\n";
    echo "This is the database username from your OLD SeAT\r\n";
    echo "\r\n";
    echo "\033[1;37m --opass\033[0m\r\n";
    echo "This is the related password\r\n";
    echo "\r\n";
    echo "\033[1;37m --nhost\033[0m\r\n";
    echo "This is the database hostname to your NEW SeAT\r\n";
    echo "\r\n";
    echo "\033[1;37m --nport\033[0m\r\n";
    echo "This is the database port to your NEW SeAT\r\n";
    echo "\r\n";
    echo "\033[1;37m --nname\033[0m\r\n";
    echo "This is the database name to your NEW SeAT\r\n";
    echo "\033[1;37m --nuser\033[0m\r\n";
    echo "This is the database username to your NEW SeAT\r\n";
    echo "\r\n";
    echo "\033[1;37m --npass\033[0m\r\n";
    echo "This is the related password\r\n";

    exit(1);
}
