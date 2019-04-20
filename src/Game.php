<?php
/**
 * Created by PhpStorm.
 * User: NIKITA
 * Date: 10.05.2018
 * Time: 19:53
 */

namespace MyApp;

use MyApp\LoveLetter\LoveLetter;
use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;

class Game implements MessageComponentInterface
{
    protected $players;

    protected $clients;

    protected $globalState = [];

    protected $game;

    public function __construct()
    {
        $this->players = new \SplObjectStorage();
        $this->clients = new \SplObjectStorage();
        $this->game = new LoveLetter();
        $this->globalState['status'] = 'Willkommen!';
    }

    /**
     * When a new connection is opened it will be passed to this method
     * @param  ConnectionInterface $conn The socket/connection that just connected to your application
     * @throws \Exception
     */
    function onOpen(ConnectionInterface $conn)
    {
        // Store the new connection to send messages to later
        $this->clients->attach($conn);
        $conn->send(json_encode([
            'global' => $this->globalState,
            'local' => [
                'id' => $this->getUniqueId($conn->resourceId)
            ]
        ]));
    }

    /**
     * This is called before or after a socket is closed (depends on how it's closed).  SendMessage to $conn will not result in an error if it has already been closed.
     * @param  ConnectionInterface $conn The socket/connection that is closing/closed
     * @throws \Exception
     */
    function onClose(ConnectionInterface $conn)
    {
        // The connection is closed, remove it, as w can no longer send it messages
        $this->clients->detach($conn);
        foreach ($this->players as $player) {
            if ($player->getClient() == $conn) {
                $player->removeClient();
                break;
            }
        }
        $this->update();
    }

    /**
     * If there is an error with one of the sockets, or somewhere in the application where an Exception is thrown,
     * the Exception is sent back down the stack, handled by the Server and bubbled back up the application through this method
     * @param  ConnectionInterface $conn
     * @param  \Exception $e
     * @throws \Exception
     */
    function onError(ConnectionInterface $conn, \Exception $e)
    {
        echo "An error has occured: {$e->getMessage()}\n";
        $conn->close();
    }

    /**
     * Triggered when a client sends data through the socket
     * @param  \Ratchet\ConnectionInterface $from The socket/connection that sent the message to your application
     * @param  string $msg The message received
     * @throws \Exception
     */
    function onMessage(ConnectionInterface $from, $msg)
    {
        $msg = json_decode($msg, true);
        if (isset($msg['id'])) {
            $this->handlePlayer($from, $msg['id']);
            if (isset($msg['name'])) {
                $player = $this->getPlayerById($msg['id']);
                $player->setName($msg['name']);
            }
        }

        if (isset($msg['action'])) {
            $params = [];
            if (key_exists('params', $msg)) {
                $params = $msg['params'];
            }
            switch ($msg['action']) {
                case 'start':
                    $players = clone $this->players;
                    foreach ($players as $p) {
                        if (!$p->getClient()) {
                            $players->detach($p);
                        }
                    }
                    $players->rewind();
                    $this->game->start($players);
                    return;
                default:
                    $this->game->handleAction($params);
                    return;
            }
        }
        $this->update();
    }

    protected function handlePlayer($conn, $id)
    {
        if (!$this->playerExists($id)) {
            $player = new Player($conn, $id);
            $this->players->attach($player);
            if ($this->players->count() === 1) {
                $player->setIsHost(true);
                $this->globalState['hostid'] = $player->getId();
            }
        } elseif (!$this->getPlayerById($id)->getClient()) {
            $player = $this->getPlayerById($id);
            $player->setClient($conn);
        }
    }

    protected function update()
    {
        $this->globalState['players'] = $this->getPlayers();
        foreach ($this->players as $player) {
            if (!$player->getClient()) {
                continue;
            }
            $player->getClient()->send(json_encode([
                'global' => $this->globalState,
                'local' => $player->getState($this->players)
            ]));
        }
        $this->game->updateState();
    }

    protected function getUniqueId($id)
    {
        foreach ($this->players as $player) {
            if ($player->getId() == $id) {
                return $id + 1;
            }
        }
        return $id;
    }

    protected function playerExists($id)
    {
        foreach ($this->players as $player) {
            if ($player->getId() == $id) {
                return true;
            }
        }
        return false;
    }

    protected function getPlayerById($id)
    {
        foreach ($this->players as $player) {
            if ($player->getId() == $id) {
                return $player;
            }
        }
        return false;
    }

    protected function getPlayers()
    {
        $players = [];
        foreach ($this->players as $player) {
            $players[] = [
                "id" => $player->getId(),
                "name" => $player->getName(),
                "connected" => (bool)$player->getClient()
            ];
        }
        return $players;
    }
}
