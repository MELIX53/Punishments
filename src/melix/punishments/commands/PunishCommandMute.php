<?php

namespace melix\punishments\commands;

use donatepr\melix\DonatePRManager;
use melix\joinmanager\JoinManager;
use melix\punishments\PunishManager;
use melix\vkmanager\VKManager;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\lang\Translatable;
use pocketmine\permission\DefaultPermissions;
use pocketmine\player\Player;
use pocketmine\Server;
use Richen\Perms\PermsMain;

class PunishCommandMute extends Command
{

    public function __construct(string $name, Translatable|string $description = "", Translatable|string|null $usageMessage = null, array $aliases = [])
    {
        $this->setPermission(DefaultPermissions::ROOT_USER);
        parent::__construct($name, $description, $usageMessage, $aliases);
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args)
    {

        if (!$sender->hasPermission("pmute") and !$sender->hasPermission(DefaultPermissions::ROOT_OPERATOR)) {
            $prefix = PermsMain::getInstance()->searchPrefixToPermission("pmute");
            $sender->sendMessage(PunishManager::PREFIX . "У вас нет доступа к данной команде, команда доступна с привилегии {$prefix}");
            return;
        }

        if (!isset($args[2])) {
            $sender->sendMessage(PunishManager::PREFIX . "Пишите §b/mute ник время§7(в минутах) §bпричина");
            return;
        }

        $server = Server::getInstance();
        $name = $args[0];

        $opponent = $server->getPlayerByPrefix($name);

        if ($opponent !== null and $opponent->isConnected()) {
            $name = $opponent->getName();
        }

        if(PunishManager::getInstance()->getMuteData($name) !== null){
            $sender->sendMessage(PunishManager::PREFIX . "Игрок §b{$name}§f уже в муте!");
            return;
        }

        if (!is_numeric($args[1])) {
            $sender->sendMessage(PunishManager::PREFIX . "Время должно быть числом!");
            return;
        }

        if((int)$args[1] > PunishManager::MAX_MUTE){
            $sender->sendMessage(PunishManager::PREFIX . "Максимальное время мута §b".PunishManager::MAX_MUTE."§f минут");
            return;
        }

        $time = (int)$args[1] * 60;

        unset($args[0]);
        unset($args[1]);
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

            if($opponent?->isConnected() and $sender->getLowerCaseName() === $opponent->getLowerCaseName()){
                $sender->sendMessage(PunishManager::PREFIX . "Вы не можете наказывать самого себя!");
                return;
            }
        }

        $broadcastMessage = PunishManager::BROADCAST_MESSAGE_MUTE;
        $broadcastMessage = str_replace("{order}", $sender->getName(), $broadcastMessage);
        $broadcastMessage = str_replace("{opponent}", $name, $broadcastMessage);
        $broadcastMessage = str_replace("{reason}", $reason, $broadcastMessage);
        $broadcastMessage = str_replace("{time}", PunishManager::parseTime(time() + $time), $broadcastMessage);
        $server->broadcastMessage($broadcastMessage);

        $warnMessage = PunishManager::WARN_MESSAGE;
        $warnMessage = str_replace("{site}", PunishManager::getInstance()->site, $warnMessage);
        $sender->sendMessage(PunishManager::PREFIX . "Вы успешно замутили игрока §b{$name}\n" . $warnMessage);

        $tableName = PunishManager::TABLE_NAME_MUTES;
        $timeGenerated = date('Y-m-d H:i:s', time());
        $timeLocking = date('Y-m-d H:i:s', time() + $time);

        $set = PunishManager::getInstance()->mysqli->prepare(
            "INSERT INTO ".$tableName."(opponentName, punishedName, reason, pardoned, url, timeGenerated, timeLocking, confirmed, port) VALUE " .
            "('$name', '".$sender->getName()."', ?, null, null, '$timeGenerated', '$timeLocking', false, '".PunishManager::$PORT."')"
        );
        $set->bind_param("s", $reason);
        $set->execute();

        if($opponent?->isConnected()) {
            PunishManager::getInstance()->setMuteCache(strtolower($name), PunishManager::getInstance()->getMuteData(strtolower($name)));
        }

        VKManager::sendChatMessage("⚠ Mute ⚠ \nИгрок {$sender->getName()}\nзамутил игрока {$name}\nПо причине: {$reason}\nРазмут: ".PunishManager::parseTime(time() + $time)."\n[Сервер " . JoinManager::NUM_SERVER . "]", JoinManager::CHAT_ADMINS);

    }
}