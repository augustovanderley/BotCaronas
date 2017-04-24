<?php
	require_once "Config.php";
	require_once "TelegramConnect.php";
	require_once "CaronaDAO.php";
	require_once "Carona.php";

	class Roteador{

		/*Espera o objeto 'message' já como array*/
		private static function processData($data){
			$processedData = array();

			/*TODO inicializar objeto telegramConnect com dados da mensagem*/
			$processedData['username'] = $data["message"]["from"]["username"];
			$processedData['chatId'] = $data["message"]["chat"]["id"];
			$processedData['userId'] = $data["message"]["from"]["id"];

			error_log( print_r( $processedData, true ) );

			return $processedData;
		}

		private static function processCommand($stringComando, &$args){
			/* Trata uma string que começa com '/', seguido por no maximo 32 numeros, letras ou '_', seguido ou não de '@nomeDoBot */
			$regexComando = '~^/(?P<comando>[\d\w_]{1,32})(?:@'. Config::getBotConfig('botName') .')?~';
			$command = NULL;
			$args = NULL;

			if(preg_match($regexComando, $stringComando, $match)){
				$command = $match['comando'];
				$stringComando = str_replace($match[0], "", $stringComando);
				$args = explode(" ", $stringComando);
			}

			error_log( print_r( $command, true ) );
			error_log( print_r( $args, true ) );
			error_log( strlen($args[1]) );
			return $command;
		}

		public static function direcionar($request){
			$args = array();
			$command = self::processCommand($request['message']['text'], $args);
			$dados = self::processData($request);

			$chat_id = $dados["chatId"];
			$user_id = $dados["userId"];
			$username = $dados['username'];
			
			/*Dividir cada comando em seu controlador*/
			if($username){
				$dao = new CaronaDAO();

				switch (strtolower($command)) {
					/*comandos padrão*/
					case 'regras':
						$regras = "Regras:
									1. Novato? Cadastre sua foto e username nas configurações.

									2. Existe um bot no grupo, digite / para que liste os comandos que ele aceita (/help por exemplo).

									3. Caronas de ida serão anunciadas depois das 19h do dia anterior.

									4. Caronas de volta (pra quem volta depois de 12h) serão anunciadas depois de 12h.

									5. Motoristas, anunciem com antecedência para as Caronas se programarem.

									6. Contribuição de R$5 pela carona é fortemente sugerida.

									7. Caronas serão preferencialmente anunciadas pelo bot de caronas.

									8. Manifeste interesse sempre que possível mencionando a @ do motorista e o chame IMEDIATAMENTE no privado para combinar rota e horário.

									9. Maneirar no flood nas horas críticas de anúncio de caronas. Os bots ajudam mas ainda não são perfeitos.

									10. Final de semana, assuntos livres!

									11. Duvidas ou sugestões para aprimorar o Bot fale com o @PeedroRod ou @Teredeby.";

						TelegramConnect::sendMessage($chat_id, $regras);
						break;
					
					case 'help':
						$help = "Utilize este Bot para agendar as caronas. A utilização é super simples e através de comandos:

								/ida [horario] [vagas] [local] --> Este comando serve para definir um horário que você está INDO para o FUNDÃO.
									Ex: /ida 10:00 2 bb
									(Inclui uma carona de ida às 10:00 com 2 vagas saindo do bb)

								/ida [horario] --> Este comando serve para definir um horário que você está INDO para o FUNDÃO. Nessa opção, não é necessário definir vagas e local.
									Ex: /ida 10:00
									(Inclui uma carona de ida às 10:00)

								Caso não seja colocado o parâmetro do horário (Ex: /ida) o bot irá apresentar a lista com as caronas registradas para o trajeto.

								/volta [horario] [vagas] [local] --> Este comando serve para definir um horário que você está VOLTANDO para CAMPO GRANDE. 
									Ex: /volta 15:00 3 bb 
									(Inclui uma carona de volta às 15:00 com 3 vagas para o bb)

								/volta [horario] --> Este comando serve para definir um horário que você está VOLTANDO para o CAMPO GRANDE. Nessa opção, não é necessário definir vagas e local.
									Ex: /volta 15:00
									(Inclui uma carona de volta às 15:00)
								
								Caso não seja colocado o parâmetro do horário (Ex: /volta) o bot irá apresentar a lista com as caronas registradas para o trajeto.

								OBS --> Quando colocar o local, lembre de não utilizar espaço, pois o Bot não entende e dirá que vc escreveu errado.

								Para o local utilize sempre letras minúsculas e para mais de um local siga o padrão : local01/local02/...
									Ex: viaduto/riodoa/bb/mendanha

								/remover [ida|volta] --> Comando utilizado para remover a carona da lista. SEMPRE REMOVA a carona depois dela ter sido realizada. O sistema não faz isso automaticamente. 
									Ex: /remover ida

								/vagas [ida|volta] [vagas] --> Este comando serve para atualizar o número de vagas de uma carona
									Ex: /vagas ida 2 
									(Altera o número de vagas da ida para 2)";
						
						TelegramConnect::sendMessage($chat_id, $help);
						break;
						
					case 'teste':
						error_log("teste");
						$texto = "Versão 1.4 - ChatId: $chat_id";

						TelegramConnect::sendMessage($chat_id, $texto);
						break;

					case 'stop':
						$texto = "GALERA, OLHA A ZOEIRA...";

						TelegramConnect::sendMessage($chat_id, $texto);
						break;
						
					case 'romulomendonca':
						$texto = "GALERA ME DEIXEM EM PAZ...";
						TelegramConnect::sendMessage($chat_id, $texto);
						break;
						
					case 'michaeldouglas':
						$texto = "NUNCA MAIS EU VOU DORMIR
							  NUNCA MAIS EU VOU DORMIR
							  IIH, QUE ISSO?
							  MICHAEL DOUGLAS";
						TelegramConnect::sendMessage($chat_id, $texto);
						break;


					/*Comandos de viagem*/
					case 'ida':
						if (count($args) == 1) {

							$resultado = $dao->getListaIda($chat_id);

							$source = Config::getBotConfig("source");
							$texto = "<b>Ida para " . $source . "</b>\n";
							foreach ($resultado as $carona){
								$texto .= (string)$carona . "\n";
							}

							TelegramConnect::sendMessage($chat_id, $texto);
						} elseif (count($args) == 2) {

							$horarioRaw = $args[1];
							$horarioRegex = '/^(?P<hora>[01]?\d|2[0-3])(?::(?P<minuto>[0-5]\d))?$/';

							$horarioValido = preg_match($horarioRegex, $horarioRaw, $resultado);

							if ($horarioValido){
								$hora = $resultado['hora'];
								$minuto = isset($resultado['minuto']) ? $resultado['minuto'] : "00";

								$travel_hour = $hora . ":" . $minuto;
				
								$dao->createCarpool($chat_id, $user_id, $username, $travel_hour, '0');

								TelegramConnect::sendMessage($chat_id, "@" . $username . " oferece carona de ida às " . $travel_hour);
							} else{
								TelegramConnect::sendMessage($chat_id, "Horário inválido.");
							}

						} elseif (count($args) == 4) {

							$horarioRaw = $args[1];
							$horarioRegex = '/^(?P<hora>[01]?\d|2[0-3])(?::(?P<minuto>[0-5]\d))?$/';

							$horarioValido = preg_match($horarioRegex, $horarioRaw, $resultado);

							$spots = $args[2];
							$location = $args[3];

							if ($horarioValido){
								$hora = $resultado['hora'];
								$minuto = isset($resultado['minuto']) ? $resultado['minuto'] : "00";

								$travel_hour = $hora . ":" . $minuto;
				
								$dao->createCarpoolWithDetails($chat_id, $user_id, $username, $travel_hour, $spots, $location, '0');

								TelegramConnect::sendMessage($chat_id, "@" . $username . " oferece carona de ida às " . $travel_hour . " com " . $spots . " vagas saindo de " . $location);
							} else{
								TelegramConnect::sendMessage($chat_id, "Horário inválido.");
							}
						} else {
							TelegramConnect::sendMessage($chat_id, "Uso: /ida [horario] [vagas] [local] \nEx: /ida 10:00 2 bb");
						}
						break;

					case 'volta':
						if (count($args) == 1) {
							$resultado = $dao->getListaVolta($chat_id);

							$source = Config::getBotConfig("source");
							$texto = "<b>Volta de " . $source . "</b>\n";
							foreach ($resultado as $carona){
								$texto .= (string)$carona . "\n";
							}

							TelegramConnect::sendMessage($chat_id, $texto);

						} elseif (count($args) == 2) {

							$horarioRaw = $args[1];
							$horarioRegex = '/^(?P<hora>[01]?\d|2[0-3])(?::(?P<minuto>[0-5]\d))?$/';

							$horarioValido = preg_match($horarioRegex, $horarioRaw, $resultado);

							if ($horarioValido){
								$hora = $resultado['hora'];
								$minuto = isset($resultado['minuto']) ? $resultado['minuto'] : "00";

								$travel_hour = $hora . ":" . $minuto;
				
								$dao->createCarpool($chat_id, $user_id, $username, $travel_hour, '1');

								TelegramConnect::sendMessage($chat_id, "@" . $username . " oferece carona de volta às " . $travel_hour);
							} else{
								TelegramConnect::sendMessage($chat_id, "Horário inválido.");
							}

						} elseif (count($args) == 4) {

							$horarioRaw = $args[1];

							$horarioRegex = '/^(?P<hora>[0-2]?\d)(:(?P<minuto>[0-5]\d))?$/';

							$horarioValido = preg_match($horarioRegex, $horarioRaw, $resultado);

							$spots = $args[2];
							$location = $args[3];

							if ($horarioValido){
								$hora = $resultado['hora'];
								$minuto = isset($resultado['minuto']) ? $resultado['minuto'] : "00";

								$travel_hour = $hora . ":" . $minuto;

								$dao->createCarpoolWithDetails($chat_id, $user_id, $username, $travel_hour, $spots, $location, '1');

								TelegramConnect::sendMessage($chat_id, "@" . $username . " oferece carona de volta às " . $travel_hour . " com " . $spots . " vagas indo até " . $location);

							}else{
								TelegramConnect::sendMessage($chat_id, "Horário inválido.");
							}
						} else {
							TelegramConnect::sendMessage($chat_id, "Uso: /volta [horario] [vagas] [local] \nEx: /volta 15:00 2 bb");
						}
						break;

					case 'vagas':
						if (count($args) == 3) {
							$spots = $args[2];
							if($args[1] == 'ida') {
								$dao->updateSpots($chat_id, $user_id, $spots, '0');
								TelegramConnect::sendMessage($chat_id, "@".$username." atualizou o número de vagas de ida para " . $spots);
							} elseif ($args[1] == 'volta') {
								$dao->updateSpots($chat_id, $user_id, $spots, '1');
								TelegramConnect::sendMessage($chat_id, "@".$username." atualizou o número de vagas de volta para " . $spots);
							} else {
								TelegramConnect::sendMessage($chat_id, "Formato: /vagas [ida|volta] [vagas]\nEx: /volta ida 2");
							}
						} else {
							TelegramConnect::sendMessage($chat_id, "Formato: /vagas [ida|volta] [vagas]\nEx: /volta ida 2");
						}
						break;

					case 'remover':
						if (count($args) == 2) {
							if($args[1] == 'ida') {
								$dao->removeCarpool($chat_id, $user_id, '0');
								TelegramConnect::sendMessage($chat_id, "@".$username." removeu sua ida");
							} elseif ($args[1] == 'volta') {
								$dao->removeCarpool($chat_id, $user_id, '1');
								TelegramConnect::sendMessage($chat_id, "@".$username." removeu sua volta");
							} else {
								TelegramConnect::sendMessage($chat_id, "Formato: /remover [ida|volta]");
							}
						} else {
							TelegramConnect::sendMessage($chat_id, "Formato: /remover [ida|volta]");
						}

						break;
				}
			} else {
				TelegramConnect::sendMessage($chat_id, "Registre seu username nas configurações do Telegram para utilizar o Bot.");
			}
		}
	}
