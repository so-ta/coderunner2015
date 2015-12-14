<?php

define('ENDPOINT','https://game.coderunner.jp/');
define('TOKEN','XXXXXXXXXXXXXXXXXXXXXXXXXXXX');

date_default_timezone_set('Asia/Tokyo');

Class Game {
	public function run(){
		while(true){
			$now      = time();
			$end_time = strtotime('2015-12-12 17:30:00');
			$rest = $end_time - $now;
			$text = ''.intval($rest%(60*60*60)/(60*60)).':'.intval($rest%(60*60)/60).':'.($rest%60);
			$text = urlencode($text);
			$this->comment($text);
			usleep(1000000); // 1秒
		}
	}

	public function comment($text){
		$url = ENDPOINT.'comment'.'?token='.TOKEN.'&text='.$text;
		$result = file_get_contents($url);
		var_dump($result);
	}
}

$game_obj = new Game();
$game_obj->run();
