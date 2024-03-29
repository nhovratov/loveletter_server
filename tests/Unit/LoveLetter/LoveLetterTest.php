<?php
/**
 * Created by PhpStorm.
 * User: NIKITA
 * Date: 12.05.2018
 * Time: 23:44
 */

use NH\LoveLetter\Card\Guardian;
use NH\LoveLetter\Card\Priest;
use NH\LoveLetter\LoveLetter;
use NH\LoveLetter\StackProvider;
use NH\Mock\MockStackProvider;
use NH\Player;
use PHPUnit\Framework\TestCase;
use Ratchet\Mock\Connection;

class LoveLetterTest extends TestCase
{
    protected $mockStackProvider;

    public function setUp(): void
    {
        $this->mockStackProvider = new MockStackProvider();
    }

    public function getGameState($player)
    {
        return json_decode($player->getClient()->last['send'], true)['global'];
    }

    /**
     * @test
     */
    public function testStartWithTwoPlayers()
    {
        $players = new SplObjectStorage();
        $game = new LoveLetter($this->mockStackProvider);
        $player1 = new Player(new Connection(), 1, '123');
        $player2 = new Player(new Connection(), 2, '234');
        $player1->setIsHost(true);
        $players->attach($player1);

        // Starting game with 1 Player should not be possible.
        $game->handleAction(['uid' => '123', 'players' => $players]);

        // Game has not started yet
        $game->handleAction(['uid' => '123', 'name' => 'John']);

        $players->attach($player2);
        $game->handleAction(['uid' => '123', 'players' => $players]);

        $state = $this->getGameState($player1);
        $this->assertCount(2, $state['players']);
        $this->assertTrue($state['gameStarted']);
        $this->assertCount(3, $state['outOfGameCards']);
        $this->assertEquals($game::SELECT_FIRST_PLAYER, $game->getWaitFor());

        $playerState = $player1->getPlayerState();
        $this->assertCount(1, $playerState->getCards());

        $cards = $playerState->getCards();
        $handCard = current($cards);
        $this->assertEquals('guardian', $handCard['name']);

        $playerState = $player2->getPlayerState();
        $this->assertCount(1, $playerState->getCards());
        $cards = $playerState->getCards();
        $handCard = current($cards);
        $this->assertEquals(Guardian::$name, $handCard['name']);

        $this->assertEquals(Guardian::$name, $state['outOfGameCards'][0]['name']);
        $this->assertEquals(Guardian::$name, $state['outOfGameCards'][1]['name']);
        $this->assertEquals(Guardian::$name, $state['outOfGameCards'][2]['name']);
    }

    /**
     * @test
     */
    public function testStartWithThreePlayers()
    {
        $players = new SplObjectStorage();
        $game = new LoveLetter($this->mockStackProvider);
        $player1 = new Player(new Connection(), 1, '123');
        $player1->setIsHost(true);
        $players->attach($player1);
        $player2 = new Player(new Connection(), 2, '234');
        $players->attach($player2);
        $players->attach(new Player(new Connection(), 3, '345'));

        $game->handleAction(['uid' => '123', 'players' => $players]);
        $state = $this->getGameState($player1);

        $this->assertCount(3, $state['players']);
        $this->assertTrue($state['gameStarted']);
        $this->assertCount(0, $state['outOfGameCards']);

        $player2->removeClient();
        $game->handleAction(['uid' => '123', 'bla' => 2]);
        $game->handleAction(['uid' => '123', 'id' => 400]);
        $game->handleAction(['uid' => '123', 'id' => 2]);
    }

    /**
     * @test
     */
    public function testSelectInvalidCardId()
    {
        $players = new SplObjectStorage();
        $this->mockStackProvider->setTestCase('guardian');
        $game = new LoveLetter($this->mockStackProvider);
        $john = new Player(new Connection(), 1, '123');
        $john->setName('John');
        $john->setIsHost(true);
        $players->attach($john);

        $mikel = new Player(new Connection(), 2, '234');
        $mikel->setName('Mikel');
        $players->attach($mikel);

        $game->handleAction(['uid' => '123', 'players' => $players]);

        // Mikel tries to hack
        $game->handleAction(['uid' => '234', 'id' => 1]);

        // John begins
        $game->handleAction(['uid' => '123', 'id' => 1]);

        // Send wrong params
        $game->handleAction(['uid' => '123', 'bla' => 1337]);

        // Send invalid key
        $game->handleAction(['uid' => '123', 'key' => 1337]);

        $this->assertEquals(LoveLetter::CHOOSE_CARD, $game->getWaitFor());
    }

    /**
     * @test
     */
    public function testGuardians()
    {
        $players = new SplObjectStorage();
        $this->mockStackProvider->setTestCase('guardian');
        $game = new LoveLetter($this->mockStackProvider);
        $john = new Player(new Connection(), 1, '123');
        $john->setName('John');
        $john->setIsHost(true);
        $players->attach($john);

        $mikel = new Player(new Connection(), 2, '234');
        $mikel->setName('Mikel');
        $players->attach($mikel);

        $game->handleAction(['uid' => '123', 'players' => $players]);

        // John begins
        $game->handleAction(['uid' => '123', 'id' => 1]);
        $state = $this->getGameState($john);
        $this->assertEquals($game::CHOOSE_CARD, $game->getWaitFor());
        $this->assertEquals(1, $state['playerTurn']);

        // John chooses Guardian card
        $game->handleAction(['uid' => '123', 'key' => 7]);
        $state = $this->getGameState($john);
        $this->assertEquals($game::CHOOSE_PLAYER, $game->getWaitFor());
        $this->assertEquals('guardian', $state['activeCard']['name']);

        // Select Mikel for Effect card
        $game->handleAction(['uid' => '123', 'id' => 2]);
        $this->assertEquals($game::CHOOSE_GUARDIAN_EFFECT_CARD, $game->getWaitFor());

        // Select Baron (wrong)
        $game->handleAction(['uid' => '123', 'card' => 'baron']);

        // No cards left, game is finished with a tie
        $state = $this->getGameState($john);
        $johnState = $john->getPlayerState();
        $mikelState = $mikel->getPlayerState();
        $this->assertEquals(1, $johnState->getWins());
        $this->assertEquals(1, $mikelState->getWins());
        $this->assertFalse($state['gameFinished']);
        $this->assertEquals($game::START_NEW_ROUND, $game->getWaitFor());
    }

    /**
     * @test
     */
    public function testGuardianWin()
    {
        $players = new SplObjectStorage();
        $this->mockStackProvider->setTestCase('guardianWin');
        $game = new LoveLetter($this->mockStackProvider);
        $john = new Player(new Connection(), 1, '123');
        $john->setName('John');
        $john->setIsHost(true);
        $players->attach($john);

        $mikel = new Player(new Connection(), 2, '234');
        $mikel->setName('Mikel');
        $players->attach($mikel);

        $game->handleAction(['uid' => '123', 'players' => $players]);

        // John begins
        $game->handleAction(['uid' => '123', 'id' => 1]);
        $state = $this->getGameState($john);
        $this->assertEquals($game::CHOOSE_CARD, $game->getWaitFor());
        $this->assertEquals(1, $state['playerTurn']);

        // John chooses Guardian card
        $game->handleAction(['uid' => '123', 'key' => 9]);
        $state = $this->getGameState($john);
        $this->assertEquals($game::CHOOSE_PLAYER, $game->getWaitFor());
        $this->assertEquals('guardian', $state['activeCard']['name']);

        // Select Mikel for Effect card
        $game->handleAction(['uid' => '123', 'id' => 2]);
        $this->assertEquals($game::CHOOSE_GUARDIAN_EFFECT_CARD, $game->getWaitFor());

        // Select Countess (right)
        $game->handleAction(['uid' => '123', 'card' => 'countess']);

        // John chooses right, he wins and round is finished
        $state = $this->getGameState($john);
        $this->assertFalse($state['gameFinished']);
        $this->assertTrue($state['gameStarted']);
        $johnState = $john->getPlayerState();
        $mikelState = $mikel->getPlayerState();
        $this->assertEquals(1, $johnState->getWins());
        $this->assertEquals(0, $mikelState->getWins());
        $this->assertEquals($game::START_NEW_ROUND, $game->getWaitFor());
        $this->assertEquals(1, $game->getActivePlayerId());

        // Round 2
        $game->handleAction(['uid' => '123']);
        $this->assertEquals($game::CHOOSE_CARD, $game->getWaitFor());
        // John chooses Guardian card
        $game->handleAction(['uid' => '123', 'key' => 9]);

        // Select Mikel for Effect card
        $game->handleAction(['uid' => '123', 'id' => 2]);

        // Select Countess (right)
        $game->handleAction(['uid' => '123', 'card' => 'countess']);

        // John chooses right, he wins and round is finished
        $johnState = $john->getPlayerState();
        $mikelState = $mikel->getPlayerState();
        $this->assertEquals(2, $johnState->getWins());
        $this->assertEquals(0, $mikelState->getWins());
        $this->assertEquals($game::START_NEW_ROUND, $game->getWaitFor());

        // Round 3
        $game->handleAction(['uid' => '123']);
        // John chooses Guardian card
        $game->handleAction(['uid' => '123', 'key' => 9]);

        // Select Mikel for Effect card
        $game->handleAction(['uid' => '123', 'id' => 2]);

        // Select Countess (right)
        $game->handleAction(['uid' => '123', 'card' => 'countess']);

        // John chooses right, he wins and round is finished
        $johnState = $john->getPlayerState();
        $mikelState = $mikel->getPlayerState();
        $this->assertEquals(3, $johnState->getWins());
        $this->assertEquals(0, $mikelState->getWins());
        $this->assertEquals($game::START_NEW_ROUND, $game->getWaitFor());

        // Round 4
        $game->handleAction(['uid' => '123']);
        // John chooses Guardian card
        $game->handleAction(['uid' => '123', 'key' => 9]);

        // Select Mikel for Effect card
        $game->handleAction(['uid' => '123', 'id' => 2]);

        // Select Countess (right)
        $game->handleAction(['uid' => '123', 'card' => 'countess']);

        // John chooses right, he wins and round is finished
        $johnState = $john->getPlayerState();
        $mikelState = $mikel->getPlayerState();
        $this->assertEquals(4, $johnState->getWins());
        $this->assertEquals(0, $mikelState->getWins());
        $this->assertEquals($game::START_NEW_ROUND, $game->getWaitFor());

        // Round 5
        $game->handleAction(['uid' => '123']);
        // John chooses Guardian card
        $game->handleAction(['uid' => '123', 'key' => 9]);

        // Select Mikel for Effect card
        $game->handleAction(['uid' => '123', 'id' => 2]);

        // Select Countess (right)
        $game->handleAction(['uid' => '123', 'card' => 'countess']);

        // John chooses right, he wins and round is finished
        $johnState = $john->getPlayerState();
        $mikelState = $mikel->getPlayerState();
        $state = $this->getGameState($john);
        $this->assertTrue($state['gameFinished']);
        $this->assertEquals(5, $johnState->getWins());
        $this->assertEquals(0, $mikelState->getWins());
        $this->assertEquals($game::START_NEW_GAME, $game->getWaitFor());

        // Start new game
        $game->handleAction(['uid' => '123', 'players' => $players]);

        // Player stats are resetted
        $state = $this->getGameState($john);
        $this->assertEmpty($state['winners']);
        $johnState = $john->getPlayerState();
        $mikelState = $mikel->getPlayerState();
        $this->assertEquals(0, $johnState->getWins());
        $this->assertEquals(0, $mikelState->getWins());
        $this->assertEquals($game->getWaitFor(), LoveLetter::SELECT_FIRST_PLAYER);

    }

    /**
     * @test
     */
    public function testMaid()
    {
        $players = new SplObjectStorage();
        $this->mockStackProvider->setTestCase('maid');
        $game = new LoveLetter($this->mockStackProvider);
        $john = new Player(new Connection(), 1, '123');
        $john->setName('John');
        $john->setIsHost(true);
        $players->attach($john);

        $mikel = new Player(new Connection(), 2, '234');
        $mikel->setName('Mikel');
        $players->attach($mikel);

        $game->handleAction(['uid' => '123', 'players' => $players]);

        // John begins
        $game->handleAction(['uid' => '123', 'id' => 1]);

        // John chooses Maid card
        $game->handleAction(['uid' => '123', 'key' => 9]);
        $state = $this->getGameState($john);
        $this->assertEquals($game::PLACE_MAID_CARD, $game->getWaitFor());
        $this->assertEquals('maid', $state['activeCard']['name']);

        // Place maid card
        $game->handleAction(['uid' => '123']);
        $state = $this->getGameState($john);
        $this->assertEquals(2, $state['playerTurn']);
        $this->assertEquals($game::CHOOSE_CARD, $game->getWaitFor());

        $this->assertCount(1, $state['protectedPlayers']);

        // Select Guardian, but no selectable player left
        $game->handleAction(['uid' => '234', 'key' => 8]);
        $game->handleAction(['uid' => '234']);

        $state = $this->getGameState($john);
        $this->assertEmpty($state['protectedPlayers']);
        $this->assertFalse($state['gameFinished']);
    }

    /**
     * @test
     */
    public function testMaidTie()
    {
        $players = new SplObjectStorage();
        $this->mockStackProvider->setTestCase('maidTie');
        $game = new LoveLetter($this->mockStackProvider);
        $john = new Player(new Connection(), 1, '123');
        $john->setName('John');
        $john->setIsHost(true);
        $players->attach($john);

        $mikel = new Player(new Connection(), 2, '234');
        $mikel->setName('Mikel');
        $players->attach($mikel);

        $game->handleAction(['uid' => '123', 'players' => $players]);

        // John begins
        $game->handleAction(['uid' => '123', 'id' => 1]);

        // #1
        // John chooses Maid card
        $game->handleAction(['uid' => '123', 'key' => 7]);

        // Next game
        $game->handleAction(['uid' => '123']);
        $state = $this->getGameState($john);
        $this->assertFalse($state['gameFinished']);

        // #2
        // John chooses Maid card
        $game->handleAction(['uid' => '123', 'key' => 7]);

        // Next game
        $game->handleAction(['uid' => '123']);
        $state = $this->getGameState($john);
        $this->assertFalse($state['gameFinished']);

        // #3
        // John chooses Maid card
        $game->handleAction(['uid' => '123', 'key' => 7]);

        // Next game
        $game->handleAction(['uid' => '123']);
        $state = $this->getGameState($john);
        $this->assertFalse($state['gameFinished']);

        // #4
        // John chooses Maid card
        $game->handleAction(['uid' => '123', 'key' => 7]);

        // Next game
        $game->handleAction(['uid' => '123']);
        $state = $this->getGameState($john);
        $this->assertFalse($state['gameFinished']);

        // #5
        // John chooses Maid card
        $game->handleAction(['uid' => '123', 'key' => 7]);
        $state = $this->getGameState($john);
        $this->assertTrue($state['gameFinished']);
        $this->assertEquals(5, $john->getPlayerState()->getWins());
        $this->assertEquals(4, $mikel->getPlayerState()->getWins());
        $this->assertCount(2, $state['winners']);
    }

    /**
     * @test
     */
    public function testPriest()
    {
        $players = new SplObjectStorage();
        $this->mockStackProvider->setTestCase('priest');
        $game = new LoveLetter($this->mockStackProvider);
        $john = new Player(new Connection(), 1, '123');
        $john->setName('John');
        $john->setIsHost(true);
        $players->attach($john);

        $mikel = new Player(new Connection(), 2, '234');
        $mikel->setName('Mikel');
        $players->attach($mikel);

        $game->handleAction(['uid' => '123', 'players' => $players]);

        // John begins
        $game->handleAction(['uid' => '123', 'id' => 1]);

        // John chooses priest card
        $game->handleAction(['uid' => '123', 'key' => 8]);
        $state = $this->getGameState($john);
        $this->assertEquals($game::CHOOSE_PLAYER, $game->getWaitFor());
        $this->assertEquals('priest', $state['activeCard']['name']);

        // John chooses Mikel to look into his card
        $game->handleAction(['uid' => '123', 'id' => 2]);
        $this->assertEquals($game::CONFIRM_DISCARD_CARD, $game->getWaitFor());
        $johnState = $john->getPlayerState();
        $this->assertEquals(current($mikel->getPlayerState()->getCards()), $johnState->getEffectVisibleCard());

        // John finishes looking at Mikels cards and discards his card
        $game->handleAction(['uid' => '123']);
        $game->handleAction(['uid' => '123']);

        $this->assertEmpty($johnState->getEffectVisibleCard());

        // Mikel chooses priest card
        $game->handleAction(['uid' => '234', 'key' => 7]);

        // Mikel chooses John to look in his card
        $game->handleAction(['uid' => '234', 'id' => 1]);

        // Mikel finishes looking
        $game->handleAction(['uid' => '234']);

        // No cards left, game is finished with a tie
        $state = $this->getGameState($john);
        $this->assertFalse($state['gameFinished']);
        $johnState = $john->getPlayerState();
        $mikelState = $mikel->getPlayerState();
        $this->assertEquals(1, $johnState->getWins());
        $this->assertEquals(1, $mikelState->getWins());
        $this->assertEquals($game::START_NEW_ROUND, $game->getWaitFor());
    }

    /**
     * @test
     */
    public function testBaronWin()
    {
        $players = new SplObjectStorage();
        $this->mockStackProvider->setTestCase('baronWin');
        $game = new LoveLetter($this->mockStackProvider);
        $john = new Player(new Connection(), 1, '123');
        $john->setName('John');
        $john->setIsHost(true);
        $players->attach($john);

        $mikel = new Player(new Connection(), 2, '234');
        $mikel->setName('Mikel');
        $players->attach($mikel);

        $game->handleAction(['uid' => '123', 'players' => $players]);

        // John begins
        $game->handleAction(['uid' => '123', 'id' => 1]);

        // John chooses baron card
        $game->handleAction(['uid' => '123', 'key' => 7]);
        $state = $this->getGameState($john);
        $this->assertEquals($game::CHOOSE_PLAYER, $game->getWaitFor());
        $this->assertEquals('baron', $state['activeCard']['name']);

        // John chooses Mikel to compare cards
        $game->handleAction(['uid' => '123', 'id' => 2]);

        // Johns card 'princess(8)' is higher than Mikels card 'guardian(1)'
        $state = $this->getGameState($john);
        $this->assertFalse($state['gameFinished']);
        $johnState = $john->getPlayerState();
        $mikelState = $mikel->getPlayerState();
        $this->assertEquals(current($mikelState->getCards()), $johnState->getEffectVisibleCard());
        $this->assertEquals(current($johnState->getCards()), $mikelState->getEffectVisibleCard());
        $this->assertEquals(1, $johnState->getWins());
        $this->assertEquals(0, $mikelState->getWins());
        $this->assertEquals($game::START_NEW_ROUND, $game->getWaitFor());
    }

    /**
     * @test
     */
    public function testBaronLoose()
    {
        $players = new SplObjectStorage();
        $this->mockStackProvider->setTestCase('baronLoose');
        $game = new LoveLetter($this->mockStackProvider);
        $john = new Player(new Connection(), 1, '123');
        $john->setName('John');
        $john->setIsHost(true);
        $players->attach($john);

        $mikel = new Player(new Connection(), 2, '234');
        $mikel->setName('Mikel');
        $players->attach($mikel);

        $game->handleAction(['uid' => '123', 'players' => $players]);

        // Mikel begins
        $game->handleAction(['uid' => '123', 'id' => 2]);

        // Mikel chooses baron card
        $game->handleAction(['uid' => '234', 'key' => 1]);

        // Mikel chooses John to compare cards
        $game->handleAction(['uid' => '234', 'id' => 1]);

        // Johns card 'princess(8)' is higher than Mikels card 'guardian(1)'
        $state = $this->getGameState($john);
        $this->assertFalse($state['gameFinished']);
        $johnState = $john->getPlayerState();
        $mikelState = $mikel->getPlayerState();
        $this->assertEquals(1, $johnState->getWins());
        $this->assertEquals(0, $mikelState->getWins());
        $this->assertEquals($game::START_NEW_ROUND, $game->getWaitFor());
        $this->assertEquals(1, $game->getActivePlayerId());
    }

    /**
     * @test
     */
    public function testBaronEqual()
    {
        $players = new SplObjectStorage();
        $this->mockStackProvider->setTestCase('baronEqual');
        $game = new LoveLetter($this->mockStackProvider);
        $john = new Player(new Connection(), 1, '123');
        $john->setName('John');
        $john->setIsHost(true);
        $players->attach($john);

        $mikel = new Player(new Connection(), 2, '234');
        $mikel->setName('Mikel');
        $players->attach($mikel);

        $game->handleAction(['uid' => '123', 'players' => $players]);

        // Mikel begins
        $game->handleAction(['uid' => '123', 'id' => 2]);

        // Mikel chooses baron card
        $game->handleAction(['uid' => '234', 'key' => 10]);

        // Mikel chooses John to compare cards
        $game->handleAction(['uid' => '234', 'id' => 1]);

        // Johns card 'princess(8)' is equal to Mikels card 'princess(8)'
        $state = $this->getGameState($john);
        $johnState = $john->getPlayerState();
        $mikelState = $mikel->getPlayerState();
        $this->assertEquals(0, $johnState->getWins());
        $this->assertEquals(0, $mikelState->getWins());

        $this->assertFalse($state['gameFinished']);

        // Mikel discards his card
        $game->handleAction(['uid' => '234']);

        $this->assertEmpty($mikelState->getEffectVisibleCard());
    }

    /**
     * @test
     */
    public function testPrincess()
    {
        $players = new SplObjectStorage();
        $this->mockStackProvider->setTestCase('princess');
        $game = new LoveLetter($this->mockStackProvider);
        $john = new Player(new Connection(), 1, '123');
        $john->setName('John');
        $john->setIsHost(true);
        $players->attach($john);

        $mikel = new Player(new Connection(), 2, '234');
        $mikel->setName('Mikel');
        $players->attach($mikel);

        $game->handleAction(['uid' => '123', 'players' => $players]);

        // John begins
        $game->handleAction(['uid' => '123', 'id' => 1]);

        // John chooses princess
        $game->handleAction(['uid' => '123', 'key' => 10]);

        $state = $this->getGameState($john);
        $this->assertFalse($state['gameFinished']);
        $this->assertTrue($state['gameStarted']);
        $johnState = $john->getPlayerState();
        $mikelState = $mikel->getPlayerState();
        $this->assertEquals(0, $johnState->getWins());
        $this->assertEquals(1, $mikelState->getWins());
    }

    /**
     * @test
     */
    public function testPrinceLoose()
    {
        $players = new SplObjectStorage();
        $this->mockStackProvider->setTestCase('princeLoose');
        $game = new LoveLetter($this->mockStackProvider);
        $john = new Player(new Connection(), 1, '123');
        $john->setName('John');
        $john->setIsHost(true);
        $players->attach($john);

        $mikel = new Player(new Connection(), 2, '234');
        $mikel->setName('Mikel');
        $players->attach($mikel);

        $game->handleAction(['uid' => '123', 'players' => $players]);

        // John begins
        $game->handleAction(['uid' => '123', 'id' => 1]);

        // John chooses prince card
        $game->handleAction(['uid' => '123', 'key' => 12]);

        // John chooses Mikel to discard his card
        $game->handleAction(['uid' => '123', 'id' => 2]);

        // Mikel discards his princess and the game is over
        $state = $this->getGameState($john);
        $this->assertFalse($state['gameFinished']);
        $johnState = $john->getPlayerState();
        $mikelState = $mikel->getPlayerState();
        $this->assertEquals(1, $johnState->getWins());
        $this->assertEquals(0, $mikelState->getWins());
    }

    /**
     * @test
     */
    public function testPrinceNormal()
    {
        $players = new SplObjectStorage();
        $this->mockStackProvider->setTestCase('princeNormal');
        $game = new LoveLetter($this->mockStackProvider);
        $john = new Player(new Connection(), 1, '123');
        $john->setName('John');
        $john->setIsHost(true);
        $players->attach($john);

        $mikel = new Player(new Connection(), 2, '234');
        $mikel->setName('Mikel');
        $players->attach($mikel);

        $game->handleAction(['uid' => '123', 'players' => $players]);

        // John begins
        $game->handleAction(['uid' => '123', 'id' => 1]);

        // John chooses prince card
        $game->handleAction(['uid' => '123', 'key' => 7]);

        // John chooses Mikel to discard his card. He has to draw from the reserve
        $game->handleAction(['uid' => '123', 'id' => 2]);

        // Mikel discards his baron and draws a king so both win
        $state = $this->getGameState($john);
        $johnState = $john->getPlayerState();
        $mikelState = $mikel->getPlayerState();
        $this->assertEquals(1, $johnState->getWins());
        $this->assertEquals(1, $mikelState->getWins());
        $this->assertFalse($state['gameFinished']);
    }

    /**
     * @test
     */
    public function testKing()
    {
        $players = new SplObjectStorage();
        $this->mockStackProvider->setTestCase('king');
        $game = new LoveLetter($this->mockStackProvider);
        $john = new Player(new Connection(), 1, '123');
        $john->setName('John');
        $john->setIsHost(true);
        $players->attach($john);

        $mikel = new Player(new Connection(), 2, '234');
        $mikel->setName('Mikel');
        $players->attach($mikel);

        $game->handleAction(['uid' => '123', 'players' => $players]);

        $this->assertEquals('prince', current($mikel->getPlayerState()->getCards())['name']);

        // John begins
        $game->handleAction(['uid' => '123', 'id' => 1]);

        // John chooses king card
        $game->handleAction(['uid' => '123', 'key' => 7]);

        // John chooses Mikel to swap cards
        $game->handleAction(['uid' => '123', 'id' => 2]);

        // Mikel swaps his prince with a baron so he looses
        $state = $this->getGameState($john);
        $this->assertFalse($state['gameFinished']);
        $johnState = $john->getPlayerState();
        $mikelState = $mikel->getPlayerState();
        $this->assertEquals(1, $johnState->getWins());
        $this->assertEquals(0, $mikelState->getWins());
        $this->assertEquals($game::START_NEW_ROUND, $game->getWaitFor());
        $this->assertEquals(1, $game->getActivePlayerId());
    }

    /**
     * @test
     */
    public function testKingLoose()
    {
        $players = new SplObjectStorage();
        $this->mockStackProvider->setTestCase('kingLoose');
        $game = new LoveLetter($this->mockStackProvider);
        $john = new Player(new Connection(), 1, '123');
        $john->setName('John');
        $john->setIsHost(true);
        $players->attach($john);

        $mikel = new Player(new Connection(), 2, '234');
        $mikel->setName('Mikel');
        $players->attach($mikel);

        $game->handleAction(['uid' => '123', 'players' => $players]);

        $this->assertEquals('prince', current($mikel->getPlayerState()->getCards())['name']);

        // John begins
        $game->handleAction(['uid' => '123', 'id' => 1]);

        // John chooses king card
        $game->handleAction(['uid' => '123', 'key' => 7]);

        // John chooses Mikel to swap cards
        $game->handleAction(['uid' => '123', 'id' => 2]);

        // Mikel swaps his prince with a baron so he looses
        $state = $this->getGameState($john);
        $this->assertFalse($state['gameFinished']);
        $johnState = $john->getPlayerState();
        $mikelState = $mikel->getPlayerState();
        $this->assertEquals(0, $johnState->getWins());
        $this->assertEquals(1, $mikelState->getWins());
        $this->assertEquals($game::START_NEW_ROUND, $game->getWaitFor());
        $this->assertEquals(2, $game->getActivePlayerId());
    }

    /**
     * @test
     */
    public function testCountess()
    {
        $players = new SplObjectStorage();
        $this->mockStackProvider->setTestCase('countess');
        $game = new LoveLetter($this->mockStackProvider);
        $john = new Player(new Connection(), 1, '123');
        $john->setName('John');
        $john->setIsHost(true);
        $players->attach($john);

        $mikel = new Player(new Connection(), 2, '234');
        $mikel->setName('Mikel');
        $players->attach($mikel);

        $game->handleAction(['uid' => '123', 'players' => $players]);

        $this->assertEquals('prince', current($mikel->getPlayerState()->getCards())['name']);

        // John begins
        $game->handleAction(['uid' => '123', 'id' => 1]);

        // John chooses prince card (invalid)
        $game->handleAction(['uid' => '123', 'key' => 2]);

        $this->assertCount(2, $john->getPlayerState()->getCards());

        // Ok, John have to choose the countess ...
        $game->handleAction(['uid' => '123', 'key' => 8]);

        $this->assertCount(1, $john->getPlayerState()->getCards());
        $this->assertEquals(LoveLetter::CONFIRM_DISCARD_CARD, $game->getWaitFor());
    }

    /**
     * @test
     */
    public function testStackProvider()
    {
        $stackProvider = new StackProvider();
        $stack = $stackProvider->getStack();
        $this->assertCount(16, $stack);
    }
}
