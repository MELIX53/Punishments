<?php

namespace melix\punishments\commands;

use donatepr\melix\DonatePRManager;
use melix\joinmanager\JoinManager;
use melix\logger\AntiLoggerManager;
use melix\punishments\PunishManager;
use melix\vkmanager\VKManager;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\lang\Translatable;
use pocketmine\permission\DefaultPermissions;
use pocketmine\player\Player;
use pocketmine\Server;
use Richen\Perms\PermsMain;

class PunishCommandPkick extends Command
{

    public function __construct(string $name, Translatable|string $description = "", Translatable|string|null $usageMessage = null, array $aliases = [])
    {
        $this->setPermission(DefaultPermissions::ROOT_USER);
        parent::__construct($name, $description, $usageMessage, $aliases);
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args)
    {

        if(!$sender->hasPermission("pkick") and !$sender->hasPermission(DefaultPermissions::ROOT_OPERATOR)){
            $prefix = PermsMain::getInstance()->searchPrefixToPermission("pkick");
            $sender->sendMessage(PunishManager::PREFIX . "У вас нет доступа к данной команде, команда доступна с привилегии {$prefix}");
            return;
        }

        if(!isset($args[1])){
            $sender->sendMessage(PunishManager::PREFIX . "Пишите §b/pkick ник §bпричина");
            return;
        }

        $server = Server::getInstance();
        $opponent = $server->getPlayerByPrefix($args[0]);

        if($opponent == null or !$opponent->isConnected()){
            $sender->sendMessage(PunishManager::PREFIX . "Игрок §b{$args[0]}§f не онлайн");
            return;
        }

        unset($args[0]);
        $reason = implode(" ", $args);

        if(strlen($reason) < 3){
            $sender->sendMessage(PunishManager::PREFIX . "Слишком короткая причина!");
            return;
        }

        if($sender instanceof Player){
            if(DonatePRManager::getInstance()->isPlayerDonatePR($sender->getLowerCaseName())){
                $sender->sendMessage(PunishManager::PREFIX . "Вы не можете использовать данную команду с бесплатной привилегии!");
                return;
            }

            if($opponent->isConnected() and $sender->getLowerCaseName() === $opponent->getLowerCaseName()){
                $sender->sendMessage(PunishManager::PREFIX . "Вы не можете наказывать самого себя!");
                return;
            }
        }

        $loginMessage = PunishManager::LOGIN_MESSAGE_KICK;
        $loginMessage = str_replace("{order}", $sender->getName(), $loginMessage);
        $loginMessage = str_replace("{reason}", $reason, $loginMessage);
        $loginMessage = str_replace("{site}", PunishManager::getInstance()->site, $loginMessage);
        AntiLoggerManager::getInstance()->removeALogger($opponent->getLowerCaseName());

        $tableName = PunishManager::TABLE_NAME_KICKS;
        $timeGenerated = date('Y-m-d H:i:s', time());
        $set = PunishManager::getInstance()->mysqli->prepare(
            "INSERT INTO ".$tableName."(opponentName, punishedName, reason, url, timeGenerated, confirmed, port) VALUES " .
            "('".$opponent->getName()."', '".$sender->getName()."', ?, null, '$timeGenerated', false, '".PunishManager::$PORT."')"
        );
        $set->bind_param("s", $reason);
        $set->execute();

        $opponent->kick($loginMessage);

        $broadcastMessage = PunishManager::BROADCAST_MESSAGE_KICK;
        $broadcastMessage = str_replace("{order}", $sender->getName(), $broadcastMessage);
        $broadcastMessage = str_replace("{opponent}", $opponent->getName(), $broadcastMessage);
        $broadcastMessage = str_replace("{reason}", $reason, $broadcastMessage);
        $server->broadcastMessage($broadcastMessage);

        $warnMessage = PunishManager::WARN_MESSAGE;
        $warnMessage = str_replace("{site}", PunishManager::getInstance()->site, $warnMessage);
        $sender->sendMessage(PunishManager::PREFIX . "Вы успешно кикнули игрока §g{$opponent->getName()}\n" . $warnMessage);

        VKManager::sendChatMessage("⚠ Kick ⚠ \nИгрок {$sender->getName()}\nкикнул игрока {$opponent->getName()}\nПо причине: {$reason}\n[Таблица {$tableName}]", JoinManager::CHAT_ADMINS);
    }
}