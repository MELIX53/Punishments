<?php

namespace melix\punishments\commands;

use donatepr\melix\DonatePRManager;
use melix\joinmanager\JoinManager;
use melix\punishments\PunishManager;
use melix\vkmanager\VKManager;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\lang\Translatable;
use pocketmine\math\Vector3;
use pocketmine\permission\DefaultPermissions;
use pocketmine\player\Player;
use pocketmine\Server;
use Richen\Perms\PermsMain;

class PunishCommandFire extends Command
{

    public function __construct(string $name, Translatable|string $description = "", Translatable|string|null $usageMessage = null, array $aliases = [])
    {
        $this->setPermission(DefaultPermissions::ROOT_USER);
        parent::__construct($name, $description, $usageMessage, $aliases);
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args)
    {

        if(!$sender->hasPermission("capi.cmd.fuck") and !$sender->hasPermission(DefaultPermissions::ROOT_OPERATOR)){
            $prefix = PermsMain::getInstance()->searchPrefixToPermission("capi.cmd.fuck");
            $sender->sendMessage(PunishManager::PREFIX . "У вас нет доступа к данной команде, команда доступна с привилегии {$prefix}");
            return;
        }

        if(!isset($args[1])){
            $sender->sendMessage(PunishManager::PREFIX . "Пишите §b/fire ник §bпричина");
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

            if($sender->getLowerCaseName() === $opponent->getLowerCaseName()){
                $sender->sendMessage(PunishManager::PREFIX . "Вы не можете наказывать самого себя!");
                return;
            }
        }

        $broadcastMessage = PunishManager::BROADCAST_MESSAGE_FIRE;
        $broadcastMessage = str_replace("{order}", $sender->getName(), $broadcastMessage);
        $broadcastMessage = str_replace("{opponent}", $opponent->getName(), $broadcastMessage);
        $broadcastMessage = str_replace("{reason}", $reason, $broadcastMessage);
        $server->broadcastMessage($broadcastMessage);

        $opponent->setOnFire(5);
        $opponent->setMotion(new Vector3(0, 1, 0));

        $warnMessage = PunishManager::WARN_MESSAGE;
        $warnMessage = str_replace("{site}", PunishManager::getInstance()->site, $warnMessage);
        $sender->sendMessage(PunishManager::PREFIX . "Вы успешно подожгли игрока §g{$opponent->getName()}\n" . $warnMessage);

        VKManager::sendChatMessage("⚠ Fire ⚠ \nИгрок {$sender->getName()}\nподжег игрока {$opponent->getName()}\nПо причине: {$reason}\n[Сервер " . JoinManager::NUM_SERVER . "]", JoinManager::CHAT_ADMINS);
    }
}