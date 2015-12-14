<?php

require_once("functions.php");
date_default_timezone_set('Asia/Tokyo');

define('ENDPOINT','https://game.coderunner.jp/');
define('TOKEN','XXXXXXXXXXXXXXXXXXXXXXXXXXXX');

Class Game {
	public $worker_list;
	public $workers;
	public $speeds;
	public $gaichu;

	public function run(){

		for( $i=0;$i<50;$i++ ){
			$this->worker_list[$i] = time();
		}

		$count=0;
		while(true){
			$this->get_info();
			echo '外注カウント'.$this->gaichu."\n";
			$rest_worker = $this->rest_worker();
			usleep(1000005); // 1秒

			echo '=====内注====='."\n";
			if( $$this->gaichu == 0 ){
				$this->get_task();
			}


			echo '=====外注====='."\n";
			$this->get_out();

			echo 'あまり'.$rest_worker."\n";
			$this->not_worker();
		}
	}

	public function is_worker( $id ){
		if( $this->worker_list[$id] > time() ){
			return false;
		}
		return true;
	}
	public function not_worker(){
		$text = '';
		$time = time();
		foreach( $this->worker_list as $key => $value ){
			if( $value < $time ){
				if( $text ){
					$text .= ',';
				}
				$text .= $key;
			}
		}
		echo '['.$text.']'."\n";
	}
	public function set_worker( $id,$sec ){
		$this->worker_list[$id] = time()+ $sec;
	}
	public function rest_worker(){
		$time = time();
		$count = 0;
		foreach( $this->worker_list as $key => $value ){
			if( $value < $time ){
				$count++;
			}
		}
		return $count;
	}

	public function get_out( $resources = 'getoutJson' ){
		$url = ENDPOINT . $resources . '?token=' . TOKEN;
		$result = file_get_contents($url);

		$result = json_decode($result);
		$result = (array)$result;
		if (!$result) {
			return false;
		}

		foreach ($result["outsources"] as $key => $value) {
			$value = (array)$value;
			$must_resources = $value['load'] / $value['time'];
			$assign_worker = [];
			$assign_text = '';
			$total_resources = 0;
			$sec = 0;
			$ok = false;
			foreach ($this->speeds[$value['pattern']] as $key2 => $value2) {
				if ($this->is_worker($key2)) {
					if ($assign_text) {
						$assign_text .= ',';
					}
					$assign_text .= $key2;
					$assign_worker[] = $key2;
					$total_resources += $value2;
				}
				if ($total_resources > $must_resources) {
					$ok = true;
					break;
				}
			}
			if( $total_resources > 0 ) {
				$sec = ($value['load'] / $total_resources);
				$worker_count = count($assign_worker);
				$cospa = $value['reward'] / ($worker_count * $sec);
				if ($cospa > 0.5) {
					$this->assign('assign', $value['id'], $assign_text);
					echo '外注されました' . "\n";
					echo 'リソース量' . $total_resources . 'で仕事' . $value['load'] . 'を開始' . intval($sec) . '秒[' . $assign_text . '] 報酬' . $value['reward'] . "\n";
					echo 'コスパ' . $cospa . "\n";
					foreach ($assign_worker as $key3 => $value3) {
						$this->set_worker($value3, $sec);
					}
				}
			}
		}
		return true;
	}


	public function get_info( $resources = 'getinfoJson' ){
		$url = ENDPOINT.$resources.'?token='.TOKEN;
		$result = file_get_contents($url);

		$result = json_decode($result);
		$result = (array)$result;
		$info = array();
		$info['score'] = $result['score'];
		$info['tasks'] = $result['outsources'];
		$this->gaichu =count($result['outsources']);

		$time = time();
		foreach( $result['workers'] as $key => $value ){
			$value = (array)$value;
			$this->workers[$value['id']] = array(
				'time' => $value['time'],
				'exp'  => $value['exp'],
			);
			$rest_work = 0;
			if( $value['time'] > 0 ){
				$rest_work = $time + $value['time']+1;
			}
			$this->worker_list[$key] = $rest_work;
			foreach( $value['speed'] as $speed_key => $speed_value ){
				$this->speeds[$speed_key][$value['id']] = $speed_value;
			}
		}
		foreach( $this->speeds as $key => $value ){
			arsort($this->speeds[$key]);
		}


		var_dump('お金:'.$info['score'].'  外注タスク:'.count($result['tasks']));

		//[仕事のID] [納期までの残り時間(秒, 小数点以下切り捨て)] [仕事量] [仕事の種類] [報酬] [賠償]

		return $result;
	}

	public function get_task( $resources = 'taketaskJson' ){
		$url = ENDPOINT.$resources.'?token='.TOKEN;
		$result = file_get_contents($url);

		$result = json_decode($result);
		$result = (array)$result;

		$must_resources = $result['load'] / $result['time'];
		$assign_worker = [];
		$assign_text = '';
		$total_resources = 0;
		$sec = 0;
		$ok = false;
		foreach( $this->speeds[$result['pattern']] as $key => $value ){
			if( $this->is_worker($key) ){
				if( $assign_text ){
					$assign_text .= ',';
				}
				$assign_text .= $key;
				$assign_worker[] = $key;
				$total_resources += $value;
			}
			if( $total_resources > $must_resources ){
				$ok = true;
				break;
			}
		}

		$sec = ($result['load']/$total_resources);
		$worker_count = count($assign_worker);
		foreach( $assign_worker as $key => $value ){
			$this->set_worker( $value , $sec );
		}
		$cospa = $result['reward'] / ($worker_count*$sec);

		$rest_worker = $this->rest_worker();

		if( $ok ){
			echo 'リソース量'.$total_resources.'で仕事'.$result['load'].'を開始'.intval($sec).'秒['.$assign_text.'] 報酬'.$result['reward']."/".$result['risk']."\n";
			echo 'コスパ'.$cospa.' 稼働可能社員'.$rest_worker."人\n";
			if( $cospa < 0.5 || $worker_count >= 5 ){
				echo 'この仕事は外注しました'."\n";
				$this->gaichu( $result['id'],intval($result['reward']*0.7) );
			}else{
				$this->assign('assign',$result['id'],$assign_text);
			}
			return true;
		}else{
			echo 'この仕事は間に合いません.外注します'.$result['id'];
			$this->gaichu( $result['id'],intval($result['reward']*0.7) );
			return false;
		}
	}

	public function gaichu( $task_id , $reward ){
		$url = ENDPOINT.'outsource?task='.$task_id.'&orderReward='.$reward.'&token='.TOKEN;
		$result = file_get_contents($url);
		var_dump($result);
	}

	public function assign( $resources = 'assign' , $task_id , $worker ){
		$url = ENDPOINT.$resources.'?task='.$task_id.'&worker='.$worker.'&token='.TOKEN;
		$result = file_get_contents($url);
		var_dump($result);

		return $result;
	}
}

$game_obj = new Game();
$game_obj->run();

console("test");