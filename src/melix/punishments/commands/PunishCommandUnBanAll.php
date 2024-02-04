<?php

namespace melix\punishments\commands;

use melix\punishments\PunishManager;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\lang\Translatable;
use pocketmine\permission\DefaultPermissions;

class PunishCommandUnBanAll extends Command
{

    public function __construct(string $name, Translatable|string $description = "", Translatable|string|null $usageMessage = null, array $aliases = [])
    {
        $this->setPermission(DefaultPermissions::ROOT_USER);
        parent::__construct($name, $description, $usageMessage, $aliases);
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args)
    {

        if (!$sender->hasPermission("admin") and !$sender->hasPermission(DefaultPermissions::ROOT_OPERATOR)) {
            $sender->sendMessage(PunishManager::PREFIX . "У вас нет доступа к данной команде");
            return;
        }

        if (!isset($args[0])) {
            $sender->sendMessage(PunishManager::PREFIX . "Пишите §b/punbanall ник");
            return;
        }

        if (($message = PunishManager::getInstance()->unAll(strtolower($args[0]), $sender->getName())) == null) {
                $sender->sendMessage(PunishManager::PREFIX . "Игрок §b{$args[0]}§f не наказан");
        }else{
            $sender->sendMessage($message);
        }
    }
}