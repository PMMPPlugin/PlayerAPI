<?php

declare(strict_types=1);

namespace presentkim\playerapi;

use pocketmine\nbt\BigEndianNBTStream;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\plugin\PluginBase;
use presentkim\playerapi\command\PoolCommand;
use presentkim\playerapi\command\subcommands\{
  ListSubCommand, LangSubCommand, ReloadSubCommand, SaveSubCommand
};
use presentkim\playerapi\lang\PluginLang;

class PlayerAPI extends PluginBase{

    /**
     * @var PlayerAPI
     */
    private static $instance;

    /**
     * @return PlayerAPI
     */
    public static function getInstance() : PlayerAPI{
        return self::$instance;
    }

    /**
     * @var PluginLang
     */
    private $language;

    /**
     * @var PoolCommand
     */
    private $mainCommand;

    /**
     * @var PoolCommand[]
     */
    private $commands = [];

    /**
     * @var CompoundTag[]
     */
    private $playerDatas = [];

    public function onLoad() : void{
        if (self::$instance === null) {
            self::$instance = $this;
        }
    }

    public function onEnable() : void{
        $this->load();
    }

    public function onDisable() : void{
        //$this->save();
    }

    /**
     * @return PluginLang
     */
    public function getLanguage() : PluginLang{
        return $this->language;
    }

    /**
     * @return string
     */
    public function getSourceFolder() : string{
        $pharPath = \Phar::running();
        if (empty($pharPath)) {
            return dirname(__FILE__, 4) . DIRECTORY_SEPARATOR;
        } else {
            return $pharPath . DIRECTORY_SEPARATOR;
        }
    }

    public function load() : void{
        $playerDataFolder = "{$this->getDataFolder()}players/";
        if (!file_exists($playerDataFolder)) {
            mkdir($playerDataFolder, 0777, true);
        }
        $this->language = new PluginLang($this);
        $this->reloadConfig();
        $this->reloadCommand();
    }

    public function reloadCommand() : void{
        if ($this->mainCommand == null) {
            $this->mainCommand = new PoolCommand($this, 'playerapi');
            $this->mainCommand->createSubCommand(ListSubCommand::class);
            $this->mainCommand->createSubCommand(LangSubCommand::class);
            $this->mainCommand->createSubCommand(ReloadSubCommand::class);
            $this->mainCommand->createSubCommand(SaveSubCommand::class);
            $this->commands[$this->mainCommand->getName()] = $this->mainCommand;
        }
        $commandMap = $this->getServer()->getCommandMap();
        $fallback = strtolower($this->getName());
        foreach ($this->commands as $commandName => $command) {
            $command->updateTranslation(true);
            if ($command->isRegistered()) {
                $commandMap->unregister($command);
            }
            $commandMap->register($fallback, $command);
        }
    }

    public function save() : void{
        $dataFolder = $this->getDataFolder();
        if (!file_exists($dataFolder)) {
            mkdir($dataFolder, 0777, true);
        }
        $this->saveConfig();
        $playerDataFolder = "{$dataFolder}players/";
        if (!file_exists($playerDataFolder)) {
            mkdir($playerDataFolder, 0777, true);
        }
        $nbtStream = new BigEndianNBTStream();
        foreach ($this->playerDatas as $playerName => $compoundTag) {
            $nbtStream->setData($compoundTag);
            file_put_contents("{$playerDataFolder}{$playerName}.dat", $nbtStream->writeCompressed());
        }
    }

    /**
     * @return PoolCommand
     */
    public function getMainCommand() : PoolCommand{
        return $this->mainCommand;
    }

    /**
     * @return PoolCommand[]
     */
    public function getCommands() : array{
        return $this->commands;
    }

    /**
     * @param string $name = ''
     *
     * @return null|PoolCommand
     */
    public function getCommand(string $name = '') : ?PoolCommand{
        return $this->commands[$name] ?? null;
    }

    /**
     * @return CompoundTag[]
     */
    public function getPlayerDatas() : array{
        return $this->playerDatas;
    }

    /**
     * @param string $playerName
     *
     * @param bool   $load = false
     *
     * @return null|CompoundTag
     */
    public function getPlayerData(string $playerName, bool $load = false) : ?CompoundTag{
        $playerName = strtolower($playerName);
        if (isset($this->playerDatas[$playerName])) {
            return $this->playerDatas[$playerName];
        } elseif ($load) {
            return $this->loadPlayerData($playerName);
        }
        return null;
    }

    /**
     * @param string      $playerName
     * @param CompoundTag $playerData
     */
    public function setPlayerData(string $playerName, CompoundTag $playerData) : void{
        $this->playerDatas[strtolower($playerName)] = $playerData;
    }

    /**
     * @param string $playerName
     *
     * @return null|CompoundTag
     */
    public function loadPlayerData(string $playerName) : ?CompoundTag{
        $playerName = strtolower($playerName);
        $file = "{$this->getDataFolder()}players/{$playerName}.dat";
        if (file_exists($file)) {
            try{
                $nbtStream = new BigEndianNBTStream();
                $nbtStream->readCompressed(file_get_contents($file));
                $this->playerDatas[$playerName] = $nbtStream->getData();
                return $this->playerDatas[$playerName];
            } catch (\Throwable $e){
                $this->getLogger()->critical($e->getMessage());
            }
        }
        return null;
    }
}