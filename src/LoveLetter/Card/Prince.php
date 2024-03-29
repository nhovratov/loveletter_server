<?php

namespace NH\LoveLetter\Card;

use NH\LoveLetter\LoveLetter;

/**
 * Wenn du die Prinzessin ablegst, scheidest du aus ...
 */
class Prince extends AbstractCard implements EffectInterface
{
    public static $id = 5;
    public static $name = 'prince';
    public static $value = 5;
    public static $text = 'Wähle einen Spieler, der seine Handkarte ablegt und eine neue Karte zieht.';

    public static function activate(LoveLetter $game, $params = [])
    {
        if ($game->getWaitFor() === LOVELETTER::CHOOSE_CARD) {
            $game->setStatus($game->getActivePlayerName() . ' sucht Spieler für Karteneffekt "' . $game->getActiveCardName() . '" aus ...');
            $game->setWaitFor(LOVELETTER::CHOOSE_ANY_PLAYER);
            return;
        }

        $chosenPlayer = $game->getPlayerById($params['id']);
        $state = $chosenPlayer->getPlayerState();
        $cards = $state->getCards();
        $card = current($cards);
        $state->discardHandCard();
        $game->setStatus('Die Karte ' . $card['name'] . ' von ' . $chosenPlayer->getName() . ' wurde abgeworfen ');
        if ($card['name'] === Princess::$name) {
            $game->addOutOfGamePlayer($chosenPlayer->getId());
            $game->setStatus($game->getStatus() . 'und ist deshalb ausgeschieden. ');
        } else {
            $state->addCard($game->drawCard());
            $game->setStatus($game->getStatus() . 'und eine neue Karte wurde gezogen. ');
        }
        $game->setWaitFor(LOVELETTER::CONFIRM_DISCARD_CARD);
        $game->setStatus($game->getStatus() . $game->getActivePlayerName() . ' muss seine Karte auf den Ablagestapel legen ...');
    }
}
