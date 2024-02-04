<?php

namespace melix\punishments\commands;

use melix\punishments\PunishManager;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\lang\Translatable;
use pocketmine\permission\DefaultPermissions;
use pocketmine\Server;

class PunishCommandUnBanOS extends Command
{

    public function __construct(string $name, Translatable|string $description = "", Translatable|string|null $usageMessage = null, array $aliases = [])
    {
        $this->setPermission(DefaultPermissions::ROOT_USER);
        parent::__construct($name, $description, $usageMessage, $aliases);
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args)
    {

        if(!$sender->hasPermission("admin") and !$sender->hasPermission(DefaultPermissions::ROOT_OPERATOR)){
            $sender->sendMessage(PunishManager::PREFIX . "У вас нет доступа к данной команде");
            return;
        }

        if(!isset($args[0])){
            $sender->sendMessage(PunishManager::PREFIX . "Пишите §b/punbanos ник");
            return;
        }

        if(PunishManager::getInstance()->unBanOs($args[0], $sender->getName())) {

            $broadcastMessage = PunishManager::BROADCAST_MESSAGE_UNBAN_OS;
            $broadcastMessage = str_replace("{order}", $sender->getName(), $broadcastMessage);
            $broadcastMessage = str_replace("{opponent}", $args[0], $broadcastMessage);
            Server::getInstance()->broadcastMessage($broadcastMessage);

            $sender->sendMessage(PunishManager::PREFIX . "Вы успешно сняли блокировку по §gOS§f в игрока §b{$args[0]}");

        }else{
            $sender->sendMessage(PunishManager::PREFIX . "Игрок §b{$args[0]}§f не наказан");
        }

    }
}