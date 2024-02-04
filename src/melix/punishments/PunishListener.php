<?php

namespace melix\punishments;

use melix\joinmanager\JoinManager;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerPreLoginEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\server\CommandEvent;
use pocketmine\player\Player;

class PunishListener implements Listener
{

    public function onPreLogin(PlayerPreLoginEvent $event): void
    {
        $name = strtolower($event->getSession()->getPlayerInfo()->getUsername());
        $deviceId = $event->getPlayerInfo()->getExtraData()[PunishManager::DEVICE_ID];

        $replace =
            fn(string $message, ?array $banData) => ($banData !== null ?
                str_replace(
                    ["{order}", "{reason}", "{time}", "{donate}", "{site}"],
                    [$banData["punishedName"], $banData["reason"], PunishManager::parseTime(strtotime($banData["timeLocking"])), JoinManager::DONATE, PunishManager::getInstance()->site],
                    $message
                ) :
                null
            );
        $message = $replace(PunishManager::LOGIN_MESSAGE_BAN_OS, PunishManager::getInstance()->getBanOSData($name, $deviceId));
        if($message == null) $message = $replace(PunishManager::LOGIN_MESSAGE_BAN, PunishManager::getInstance()->getBanData($name));

        if ($message !== null) {
            $event->setKickFlag(0, $message);
        }
    }

    public function onJoin(PlayerJoinEvent $event){
        $player = $event->getPlayer();
        $name = $player->getLowerCaseName();
        $muteData = PunishManager::getInstance()->getMuteData($name);
        if($muteData !== null){
            PunishManager::getInstance()->setMuteCache($name, $muteData);
            $player->sendMessage(PunishManager::PREFIX . "Ваш чат заблокан, блокировка закончится через: §g" . PunishManager::parseTime(strtotime($muteData["timeLocking"])));
        }
    }

    public function onQuit(PlayerQuitEvent $event){
        $player = $event->getPlayer();
        $name = $player->getLowerCaseName();
        PunishManager::getInstance()->remMuteCache($name);
    }

    /**
     * @param PlayerChatEvent $event
     * @priority LOWEST
     * @ignoreCancelled true
     */
    public function onChat(PlayerChatEvent $event)
    {
        $player = $event->getPlayer();
        $name = $player->getLowerCaseName();

        if (($muteCache = PunishManager::getInstance()->getMuteCache($name)) !== null) {
            $message = PunishManager::MESSAGE_MUTE;
            $message = str_replace("{order}", $muteCache["punishedName"], $message);
            $message = str_replace("{reason}", $muteCache["reason"], $message);
            $message = str_replace("{time}", PunishManager::parseTime($muteCache["timeLocking"]), $message);
            $message = str_replace("{site}", PunishManager::getInstance()->site, $message);
            $player->sendMessage($message);
            $event->cancel();
        }
    }

    /**
     * @param CommandEvent $event
     * @priority LOWEST
     * @ignoreCancelled true
     */
    public function onCommandPreprocess(CommandEvent $event)
    {
        $player = $event->getSender();

        if ($player instanceof Player) {
            $commands = ["", "tell", "buysay", "say"];
            $args = explode(" ", str_replace("\"", "", $event->getCommand()));
            if (in_array($args[0], $commands)) {
                if (($muteData = PunishManager::getInstance()->getMuteCache($player->getLowerCaseName())) !== null) {
                    $message = PunishManager::MESSAGE_MUTE;
                    $message = str_replace("{order}", $muteData["punishedName"], $message);
                    $message = str_replace("{reason}", $muteData["reason"], $message);
                    $message = str_replace("{time}", PunishManager::parseTime($muteData["timeLocking"]), $message);
                    $player->sendMessage($message);
                    $event->cancel();
                }
            }
        }
    }

}