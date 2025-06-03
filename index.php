<?php
// Configuración inicial del juego (PHP backend)
session_start();

// Inicializar el juego si es la primera vez
if (!isset($_SESSION['game_initialized'])) {
    $_SESSION['players'] = [];
    $_SESSION['obstacles'] = [];
    $_SESSION['game_started'] = false;
    $_SESSION['game_initialized'] = true;
    $_SESSION['last_obstacle_time'] = time();
}

// Función para simular Python (aquí usamos PHP)
function python_simulated_ai($difficulty) {
    $actions = ['move_left', 'move_right', 'boost', 'shield'];
    $weights = [
        'easy' => [30, 30, 20, 20],
        'medium' => [25, 25, 25, 25],
        'hard' => [20, 20, 30, 30]
    ];
    
    $random = mt_rand(1, 100);
    $cumulative = 0;
    foreach ($weights[$difficulty] as $index => $weight) {
        $cumulative += $weight;
        if ($random <= $cumulative) {
            return $actions[$index];
        }
    }
    return $actions[0];
}

// Manejar acciones AJAX
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'join_game':
            $playerId = uniqid('player_');
            $_SESSION['players'][$playerId] = [
                'id' => $playerId,
                'name' => substr($_POST['name'], 0, 12),
                'position' => 0,
                'score' => 0,
                'lives' => 3,
                'color' => sprintf('#%06X', mt_rand(0, 0xFFFFFF)),
                'last_action' => time(),
                'boost' => 100,
                'shield' => false
            ];
            echo json_encode(['success' => true, 'playerId' => $playerId]);
            exit;
            
        case 'get_state':
            echo json_encode([
                'players' => $_SESSION['players'],
                'obstacles' => $_SESSION['obstacles'],
                'game_started' => $_SESSION['game_started']
            ]);
            exit;
            
        case 'player_action':
            if (isset($_SESSION['players'][$_POST['playerId']])) {
                $player = &$_SESSION['players'][$_POST['playerId']];
                $player['last_action'] = time();
                
                switch ($_POST['type']) {
                    case 'move_left':
                        $player['position'] = max(0, $player['position'] - 10);
                        break;
                    case 'move_right':
                        $player['position'] = min(100, $player['position'] + 10);
                        break;
                    case 'boost':
                        if ($player['boost'] > 0) {
                            $player['score'] += 5;
                            $player['boost'] -= 1;
                        }
                        break;
                    case 'shield':
                        $player['shield'] = true;
                        break;
                }
                
                // Actualizar puntuación
                $player['score'] += 1;
            }
            echo json_encode(['success' => true]);
            exit;
            
        case 'start_game':
            $_SESSION['game_started'] = true;
            echo json_encode(['success' => true]);
            exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Space Race - Carrera Espacial Multijugador</title>
    <style>
        /* Estilos CSS profesionales */
        :root {
            --space-dark: #0a0e24;
            --space-light: #1a237e;
            --neon-blue: #00b4ff;
            --neon-pink: #ff00aa;
            --neon-green: #00ff88;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Orbitron', sans-serif;
        }
        
        body {
            background: var(--space-dark);
            color: white;
            overflow: hidden;
            height: 100vh;
            background-image: 
                radial-gradient(circle at 10% 20%, rgba(26, 35, 126, 0.8) 0%, transparent 20%),
                radial-gradient(circle at 90% 80%, rgba(106, 27, 154, 0.6) 0%, transparent 20%),
                linear-gradient(to bottom, rgba(0, 0, 0, 0.9) 0%, rgba(10, 14, 36, 0.9) 100%);
        }
        
        @font-face {
            font-family: 'Orbitron';
            src: url('https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700&display=swap');
        }
        
        #game-container {
            position: relative;
            width: 100%;
            height: 100vh;
            overflow: hidden;
        }
        
        #game-screen {
            position: relative;
            width: 100%;
            height: 100%;
            display: none;
        }
        
        #start-screen {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            background: rgba(10, 14, 36, 0.9);
            z-index: 100;
        }
        
        .title {
            font-size: 4rem;
            margin-bottom: 2rem;
            background: linear-gradient(to right, var(--neon-blue), var(--neon-pink));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            text-shadow: 0 0 10px rgba(0, 180, 255, 0.5);
            animation: pulse 2s infinite alternate;
        }
        
        @keyframes pulse {
            0% { text-shadow: 0 0 10px rgba(0, 180, 255, 0.5); }
            100% { text-shadow: 0 0 20px rgba(0, 180, 255, 0.8), 0 0 30px rgba(255, 0, 170, 0.6); }
        }
        
        .input-group {
            margin: 1rem 0;
            position: relative;
        }
        
        input {
            background: rgba(255, 255, 255, 0.1);
            border: 2px solid var(--neon-blue);
            border-radius: 5px;
            padding: 0.8rem 1.2rem;
            color: white;
            font-size: 1rem;
            width: 300px;
            outline: none;
            transition: all 0.3s;
        }
        
        input:focus {
            border-color: var(--neon-pink);
            box-shadow: 0 0 10px rgba(255, 0, 170, 0.5);
        }
        
        button {
            background: linear-gradient(45deg, var(--neon-blue), var(--neon-pink));
            border: none;
            border-radius: 5px;
            padding: 0.8rem 2rem;
            color: white;
            font-size: 1rem;
            font-weight: bold;
            cursor: pointer;
            margin: 0.5rem;
            transition: all 0.3s;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        button:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 180, 255, 0.4);
        }
        
        button:active {
            transform: translateY(0);
        }
        
        .player {
            position: absolute;
            width: 40px;
            height: 60px;
            bottom: 20px;
            transition: left 0.2s ease-out;
            z-index: 10;
        }
        
        .player-ship {
            width: 100%;
            height: 100%;
            background: linear-gradient(45deg, var(--color), rgba(255, 255, 255, 0.8));
            clip-path: polygon(50% 0%, 100% 100%, 50% 70%, 0% 100%);
            position: relative;
        }
        
        .player-shield {
            position: absolute;
            top: -10px;
            left: -10px;
            width: 60px;
            height: 80px;
            border-radius: 50%;
            border: 2px dashed rgba(0, 255, 200, 0.7);
            animation: shield-pulse 1.5s infinite;
            display: none;
        }
        
        @keyframes shield-pulse {
            0% { transform: scale(1); opacity: 0.7; }
            50% { transform: scale(1.05); opacity: 1; }
            100% { transform: scale(1); opacity: 0.7; }
        }
        
        .player-boost {
            position: absolute;
            bottom: -15px;
            left: 15px;
            width: 10px;
            height: 20px;
            background: linear-gradient(to top, orange, yellow);
            border-radius: 50% 50% 0 0;
            filter: blur(2px);
            transform-origin: bottom center;
            animation: boost-flame 0.1s infinite alternate;
        }
        
        @keyframes boost-flame {
            0% { transform: scaleY(1); opacity: 0.8; }
            100% { transform: scaleY(1.3); opacity: 1; }
        }
        
        .obstacle {
            position: absolute;
            border-radius: 50%;
            z-index: 5;
        }
        
        .meteor {
            background: linear-gradient(45deg, #6d4c41, #3e2723);
            box-shadow: inset -5px -5px 10px rgba(0, 0, 0, 0.5);
        }
        
        .star {
            background: white;
            box-shadow: 0 0 10px 2px white, 0 0 20px 5px var(--color);
            animation: twinkle 1s infinite alternate;
        }
        
        @keyframes twinkle {
            0% { opacity: 0.7; transform: scale(1); }
            100% { opacity: 1; transform: scale(1.1); }
        }
        
        .score-board {
            position: absolute;
            top: 20px;
            left: 20px;
            background: rgba(0, 0, 0, 0.7);
            padding: 1rem;
            border-radius: 10px;
            border: 1px solid var(--neon-blue);
            z-index: 20;
            max-height: 70vh;
            overflow-y: auto;
        }
        
        .score-item {
            display: flex;
            justify-content: space-between;
            margin: 0.5rem 0;
            padding: 0.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .player-info {
            display: flex;
            align-items: center;
        }
        
        .player-color {
            width: 15px;
            height: 15px;
            border-radius: 50%;
            margin-right: 10px;
        }
        
        .controls {
            position: absolute;
            bottom: 20px;
            left: 0;
            width: 100%;
            display: flex;
            justify-content: center;
            z-index: 20;
        }
        
        .control-btn {
            background: rgba(0, 0, 0, 0.5);
            border: 2px solid var(--neon-blue);
            border-radius: 50%;
            width: 60px;
            height: 60px;
            margin: 0 10px;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 1.5rem;
            color: white;
            cursor: pointer;
            user-select: none;
        }
        
        .boost-bar {
            position: absolute;
            bottom: 100px;
            left: 50%;
            transform: translateX(-50%);
            width: 200px;
            height: 20px;
            background: rgba(0, 0, 0, 0.5);
            border: 2px solid var(--neon-blue);
            border-radius: 10px;
            overflow: hidden;
        }
        
        .boost-fill {
            height: 100%;
            background: linear-gradient(to right, var(--neon-blue), var(--neon-pink));
            width: 100%;
            transition: width 0.3s;
        }
        
        .game-over {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            display: none;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            z-index: 200;
        }
        
        .game-over h2 {
            font-size: 3rem;
            color: var(--neon-pink);
            margin-bottom: 2rem;
            text-shadow: 0 0 10px rgba(255, 0, 170, 0.7);
        }
        
        .leaderboard {
            background: rgba(0, 0, 0, 0.7);
            padding: 2rem;
            border-radius: 10px;
            border: 2px solid var(--neon-blue);
            width: 80%;
            max-width: 500px;
        }
        
        .leaderboard-item {
            display: flex;
            justify-content: space-between;
            padding: 0.8rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .leaderboard-item:first-child {
            color: gold;
            font-weight: bold;
        }
        
        .leaderboard-item:nth-child(2) {
            color: silver;
        }
        
        .leaderboard-item:nth-child(3) {
            color: #cd7f32; /* bronze */
        }
        
        .stars-bg {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1;
        }
        
        .star-bg {
            position: absolute;
            background: white;
            border-radius: 50%;
            animation: twinkle-bg 3s infinite alternate;
        }
        
        @keyframes twinkle-bg {
            0% { opacity: 0.1; }
            100% { opacity: 0.8; }
        }
    </style>
</head>
<body>
    <div id="game-container">
        <!-- Fondo de estrellas dinámico -->
        <div class="stars-bg" id="stars-bg"></div>
        
        <!-- Pantalla de inicio -->
        <div id="start-screen">
            <h1 class="title">SPACE RACE</h1>
            <div class="input-group">
                <input type="text" id="player-name" placeholder="Tu nombre de piloto" maxlength="12">
            </div>
            <button id="start-btn">Iniciar Carrera</button>
            <p style="margin-top: 2rem; color: rgba(255, 255, 255, 0.7);">
                Usa los controles o las teclas ← → para moverte, ESPACIO para turbo
            </p>
        </div>
        
        <!-- Pantalla de juego -->
        <div id="game-screen">
            <!-- Tabla de puntuaciones -->
            <div class="score-board" id="score-board"></div>
            
            <!-- Barra de turbo -->
            <div class="boost-bar">
                <div class="boost-fill" id="boost-fill"></div>
            </div>
            
            <!-- Controles móviles -->
            <div class="controls">
                <div class="control-btn" id="left-btn">←</div>
                <div class="control-btn" id="boost-btn">▲</div>
                <div class="control-btn" id="right-btn">→</div>
            </div>
        </div>
        
        <!-- Pantalla de game over -->
        <div class="game-over" id="game-over">
            <h2>CARRERA TERMINADA</h2>
            <div class="leaderboard" id="leaderboard"></div>
            <button id="play-again-btn">Nueva Carrera</button>
        </div>
    </div>

    <script>
        // Variables globales del juego
        let playerId = null;
        let gameActive = false;
        let lastUpdate = 0;
        let keysPressed = {};
        let boostActive = false;
        let lastBoostTime = 0;
        let lastShieldTime = 0;
        
        // Elementos del DOM
        const startScreen = document.getElementById('start-screen');
        const gameScreen = document.getElementById('game-screen');
        const gameOverScreen = document.getElementById('game-over');
        const playerNameInput = document.getElementById('player-name');
        const startBtn = document.getElementById('start-btn');
        const playAgainBtn = document.getElementById('play-again-btn');
        const scoreBoard = document.getElementById('score-board');
        const leaderboard = document.getElementById('leaderboard');
        const boostFill = document.getElementById('boost-fill');
        const leftBtn = document.getElementById('left-btn');
        const rightBtn = document.getElementById('right-btn');
        const boostBtn = document.getElementById('boost-btn');
        const starsBg = document.getElementById('stars-bg');
        
        // Crear fondo de estrellas
        function createStarBackground() {
            starsBg.innerHTML = '';
            const starCount = Math.floor(window.innerWidth * window.innerHeight / 1000);
            
            for (let i = 0; i < starCount; i++) {
                const star = document.createElement('div');
                star.className = 'star-bg';
                
                const size = Math.random() * 2;
                const posX = Math.random() * 100;
                const posY = Math.random() * 100;
                const opacity = Math.random() * 0.8 + 0.1;
                const duration = Math.random() * 5 + 2;
                
                star.style.width = `${size}px`;
                star.style.height = `${size}px`;
                star.style.left = `${posX}%`;
                star.style.top = `${posY}%`;
                star.style.opacity = opacity;
                star.style.animationDuration = `${duration}s`;
                
                starsBg.appendChild(star);
            }
        }
        
        // Unirse al juego
        async function joinGame(playerName) {
            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=join_game&name=${encodeURIComponent(playerName)}`
                });
                
                const data = await response.json();
                
                if (data.success) {
                    playerId = data.playerId;
                    return true;
                }
            } catch (error) {
                console.error('Error al unirse al juego:', error);
            }
            return false;
        }
        
        // Iniciar juego
        async function startGame() {
            const playerName = playerNameInput.value.trim() || 'Piloto_' + Math.floor(Math.random() * 1000);
            
            if (await joinGame(playerName)) {
                startScreen.style.display = 'none';
                gameScreen.style.display = 'block';
                
                // Iniciar el juego en el servidor
                await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=start_game'
                });
                
                gameActive = true;
                lastUpdate = Date.now();
                gameLoop();
            } else {
                alert('Error al unirse al juego. Intenta recargar la página.');
            }
        }
        
        // Bucle principal del juego
        async function gameLoop() {
            if (!gameActive) return;
            
            const now = Date.now();
            const deltaTime = (now - lastUpdate) / 1000;
            lastUpdate = now;
            
            // Manejar entrada del teclado
            handlePlayerInput(deltaTime);
            
            // Obtener estado del juego
            const gameState = await getGameState();
            
            if (gameState) {
                // Renderizar juego
                renderGame(gameState);
                
                // Verificar si el juego sigue activo
                if (gameState.game_started === false) {
                    endGame(gameState.players);
                    return;
                }
            }
            
            requestAnimationFrame(gameLoop);
        }
        
        // Manejar entrada del jugador
        function handlePlayerInput(deltaTime) {
            // Teclado
            if (keysPressed['ArrowLeft'] || keysPressed['a']) {
                sendPlayerAction('move_left');
            }
            if (keysPressed['ArrowRight'] || keysPressed['d']) {
                sendPlayerAction('move_right');
            }
            if ((keysPressed[' '] || keysPressed['w'] || boostActive) && Date.now() - lastBoostTime > 100) {
                sendPlayerAction('boost');
                lastBoostTime = Date.now();
            }
            if (keysPressed['s'] && Date.now() - lastShieldTime > 5000) {
                sendPlayerAction('shield');
                lastShieldTime = Date.now();
            }
        }
        
        // Enviar acción del jugador al servidor
        async function sendPlayerAction(actionType) {
            if (!playerId) return;
            
            try {
                await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=player_action&playerId=${playerId}&type=${actionType}`
                });
            } catch (error) {
                console.error('Error al enviar acción:', error);
            }
        }
        
        // Obtener estado del juego desde el servidor
        async function getGameState() {
            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=get_state'
                });
                
                return await response.json();
            } catch (error) {
                console.error('Error al obtener estado del juego:', error);
                return null;
            }
        }
        
        // Renderizar el estado del juego
        function renderGame(state) {
            // Limpiar obstáculos y jugadores existentes
            document.querySelectorAll('.player-container, .obstacle').forEach(el => el.remove());
            
            // Renderizar jugadores
            Object.values(state.players).forEach(player => {
                const playerContainer = document.createElement('div');
                playerContainer.className = 'player-container';
                playerContainer.style.left = `${player.position}%`;
                playerContainer.style.bottom = '20px';
                
                const playerElement = document.createElement('div');
                playerElement.className = 'player';
                playerElement.innerHTML = `
                    <div class="player-ship" style="--color: ${player.color}"></div>
                    <div class="player-shield" style="${player.shield ? 'display: block;' : 'display: none;'}"></div>
                    ${player.id === playerId ? '<div class="player-boost"></div>' : ''}
                `;
                
                playerContainer.appendChild(playerElement);
                gameScreen.appendChild(playerContainer);
                
                // Mostrar nombre del jugador
                const nameTag = document.createElement('div');
                nameTag.style.position = 'absolute';
                nameTag.style.bottom = '70px';
                nameTag.style.left = '50%';
                nameTag.style.transform = 'translateX(-50%)';
                nameTag.style.color = 'white';
                nameTag.style.textShadow = '0 0 5px black';
                nameTag.style.fontSize = '12px';
                nameTag.style.whiteSpace = 'nowrap';
                nameTag.textContent = player.name;
                playerContainer.appendChild(nameTag);
                
                // Mostrar vidas
                const livesTag = document.createElement('div');
                livesTag.style.position = 'absolute';
                livesTag.style.bottom = '85px';
                livesTag.style.left = '50%';
                livesTag.style.transform = 'translateX(-50%)';
                livesTag.style.display = 'flex';
                
                for (let i = 0; i < 3; i++) {
                    const life = document.createElement('div');
                    life.style.width = '10px';
                    life.style.height = '10px';
                    life.style.borderRadius = '50%';
                    life.style.margin = '0 2px';
                    life.style.backgroundColor = i < player.lives ? player.color : 'rgba(255, 255, 255, 0.2)';
                    livesTag.appendChild(life);
                }
                
                playerContainer.appendChild(livesTag);
            });
            
            // Renderizar obstáculos
            state.obstacles.forEach(obstacle => {
                const obstacleElement = document.createElement('div');
                obstacleElement.className = `obstacle ${obstacle.type}`;
                obstacleElement.style.left = `${obstacle.position}%`;
                obstacleElement.style.top = `${obstacle.top}px`;
                obstacleElement.style.width = `${obstacle.size}px`;
                obstacleElement.style.height = `${obstacle.size}px`;
                obstacleElement.style.setProperty('--color', obstacle.color || '#ffffff');
                gameScreen.appendChild(obstacleElement);
            });
            
            // Actualizar tabla de puntuaciones
            updateScoreBoard(state.players);
            
            // Actualizar barra de turbo para el jugador actual
            if (playerId && state.players[playerId]) {
                const boost = state.players[playerId].boost;
                boostFill.style.width = `${boost}%`;
                boostFill.style.backgroundColor = boost > 30 ? 
                    'linear-gradient(to right, var(--neon-blue), var(--neon-pink))' : 
                    'linear-gradient(to right, #ff3300, #ff6600)';
            }
        }
        
        // Actualizar tabla de puntuaciones
        function updateScoreBoard(players) {
            const sortedPlayers = Object.values(players).sort((a, b) => b.score - a.score);
            
            scoreBoard.innerHTML = '';
            sortedPlayers.forEach((player, index) => {
                const playerElement = document.createElement('div');
                playerElement.className = 'score-item';
                playerElement.innerHTML = `
                    <div class="player-info">
                        <div class="player-color" style="background: ${player.color}"></div>
                        <span>${player.name}</span>
                    </div>
                    <div>${player.score}</div>
                `;
                
                if (player.id === playerId) {
                    playerElement.style.backgroundColor = 'rgba(0, 180, 255, 0.2)';
                    playerElement.style.borderLeft = `3px solid ${player.color}`;
                }
                
                scoreBoard.appendChild(playerElement);
            });
        }
        
        // Finalizar el juego
        function endGame(players) {
            gameActive = false;
            gameScreen.style.display = 'none';
            gameOverScreen.style.display = 'flex';
            
            // Mostrar leaderboard
            const sortedPlayers = Object.values(players).sort((a, b) => b.score - a.score);
            
            leaderboard.innerHTML = '';
            sortedPlayers.forEach((player, index) => {
                const playerElement = document.createElement('div');
                playerElement.className = 'leaderboard-item';
                playerElement.innerHTML = `
                    <span>${index + 1}. ${player.name}</span>
                    <span>${player.score} pts</span>
                `;
                leaderboard.appendChild(playerElement);
            });
        }
        
        // Event listeners
        startBtn.addEventListener('click', startGame);
        playAgainBtn.addEventListener('click', () => {
            location.reload();
        });
        
        // Controles táctiles
        leftBtn.addEventListener('touchstart', () => { keysPressed['ArrowLeft'] = true; });
        leftBtn.addEventListener('touchend', () => { keysPressed['ArrowLeft'] = false; });
        leftBtn.addEventListener('mousedown', () => { keysPressed['ArrowLeft'] = true; });
        leftBtn.addEventListener('mouseup', () => { keysPressed['ArrowLeft'] = false; });
        leftBtn.addEventListener('mouseleave', () => { keysPressed['ArrowLeft'] = false; });
        
        rightBtn.addEventListener('touchstart', () => { keysPressed['ArrowRight'] = true; });
        rightBtn.addEventListener('touchend', () => { keysPressed['ArrowRight'] = false; });
        rightBtn.addEventListener('mousedown', () => { keysPressed['ArrowRight'] = true; });
        rightBtn.addEventListener('mouseup', () => { keysPressed['ArrowRight'] = false; });
        rightBtn.addEventListener('mouseleave', () => { keysPressed['ArrowRight'] = false; });
        
        boostBtn.addEventListener('touchstart', () => { boostActive = true; });
        boostBtn.addEventListener('touchend', () => { boostActive = false; });
        boostBtn.addEventListener('mousedown', () => { boostActive = true; });
        boostBtn.addEventListener('mouseup', () => { boostActive = false; });
        boostBtn.addEventListener('mouseleave', () => { boostActive = false; });
        
        // Controles de teclado
        document.addEventListener('keydown', (e) => {
            keysPressed[e.key] = true;
        });
        
        document.addEventListener('keyup', (e) => {
            keysPressed[e.key] = false;
        });
        
        // Permitir iniciar con Enter
        playerNameInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                startGame();
            }
        });
        
        // Inicializar juego
        createStarBackground();
        
        // Simular generación de obstáculos (esto normalmente se haría en el servidor)
        setInterval(async () => {
            if (gameActive && playerId) {
                // En un juego real, esto se manejaría en el servidor
                // Aquí es solo para demostración
                const gameState = await getGameState();
                if (gameState && gameState.game_started) {
                    // El servidor debería manejar la generación de obstáculos
                    // Esta parte es solo para simular cómo podría funcionar
                }
            }
        }, 1000);
    </script>
</body>
</html>