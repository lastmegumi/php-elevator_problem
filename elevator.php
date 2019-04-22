	<style>
	table{width:100;}
		td{border:1px solid #ddd;font-size: 13px; width:200px !important;min-width: 100px;}
	</style>
<?php
$FL = $_POST['fl']? $_POST['fl']:8;
$EL = $_POST['el']? $_POST['el']:5;
$CL = $_POST['cl']? $_POST['cl']:100;
$ST = $_POST['st']? $_POST['st']:20;
$IN = $_POST['in']? $_POST['in']:10;
$TR = $_POST['tr']? $_POST['tr']:1000;
?>
<form method="POST">
	TOTAL Floor <input name="fl" placeholder = "floor count" value="<?php echo $FL; ?>"/>
	TOTAL Customer <input name="cl" placeholder = "customer count" value="<?php echo $CL; ?>"/><br/>
	TOTAL Elevator <input name="el" placeholder = "elevator count" value="<?php echo $EL; ?>"/>
	Cost on every level  <input name="st" placeholder = "elevator level" value="<?php echo $ST; ?>"/><br/>
	Stay Time  <input name="in" placeholder = "stay every level" value="<?php echo $IN; ?>"/>
	Time range for customer arrival<input name="tr" placeholder = "Total time" value="<?php echo $TR; ?>"/>
	<button>RUN</button>
</form>
<?php
@define(FC, $FL);
@define(CC, $CL);
@define(EC, $EL);
@define(TT, $TR);
@define(STEP, $ST);
@define(INTERVAL, $IN);
@define(ELMAX, 20 );
class ele{
	public $id;
	public $current_floor = 1;
	public $passed_floor = 1;
	public $temp = 0;
	public $target_list = array();
	public $status = false;
	public $reached = true;
	public $stay = true;
	public $direction = '';
	public $customer_list = array();
	public $open = false;
	public $close = true;

	function __construct($id){
		$this->id = $id;
	}

	function get_status(){
		$r = '';
		$r .= $this->stay?'stay':'';
		$r .= $this->status?'<strong>run</strong>':'off';
		$r .= $this->reached?'reached':'';
		$r .= '-' . $this->temp; 
		return $r;

	}
	function add_target($num){
		$this->target_list[] = $num;
		$this->target_list = array_unique($this->target_list);
		if(count($this->target_list) == 1):
			if($num > $this->current_floor):
				$this->direction = 'up';
			elseif($num < $this->current_floor):
				$this->direction = 'down';
			endif;
		endif;
	}

	function check_target($clock){
		if($this->reached && in_array($this->current_floor, $this->target_list)):
			if(!$this->stay):
				$this->temp = $clock;
				$this->stay = true;
			endif;

			foreach ($this->customer_list as $c) :
				if($c->target_floor == $this->current_floor):
					$this->release($c);
				endif;
			endforeach;
			unset($this->target_list[array_search($this->current_floor, $this->target_list)]);
			$this->is_finished();
			return true;
		endif;
		return false;
	}

	function check_floor($clock){
		if(!$this->status):
			return;
		endif;
		if($this->stay && $clock - $this->temp == INTERVAL):
			$this->stay = false;
			$this->start($clock);
		endif;
		if($clock - $this->temp == STEP && !$this->stay && $this->status):
			if($this->direction == 'up'):
				$this->current_floor++;
				$this->temp = $clock;
				$this->reached = true;
			elseif($this->direction == 'down'):
				$this->current_floor--;
				$this->temp = $clock;
				$this->reached = true;
			endif;
			$this->stop($clock);
			return;
		else: $this->reached = false;
		endif;
	}

	function distance($f){
		switch ($e->direction) {
			case 'up':
				if($f->direction['up'] && $f->num > $e->current_floor):
					return abs($this->current_floor - $f->num);
				endif;
				break;
			case 'down':
				if($f->direction['down'] && $f->num < $e->current_floor):
					return abs($this->current_floor - $f->num);
				endif;
				break;
			default:
				if(!$this->direction):

				return abs($this->current_floor - $f->num);
				endif;
				break;
		}
		return false;
	}

	function load($c){
		if($this->direction == $c->direction || !$this->direction):
			if(!$this->stay):
				$this->stay = true;
			endif;
			$this->customer_list[$c->cid] = $c;
			$this->add_target($c->target_floor);
			return true;
		else: return false;
		endif;
	}


	function release($c){
		unset($this->customer_list[$c->cid]);
	}

	function start($clock){
		if(!$this->status){
			$this->temp = $clock;
			$this->status = true;
		}

		$this->status = true;
		if(!$this->stay && $clock - $this->temp == INTERVAL):
			$this->reached = false;
			$this->temp = $clock;
			$this->stay = false;
		endif;
	}

	function stop($clock){
		$this->temp = $clock;
		$this->reached = true;
	}

	function is_finished(){
		if($this->target_list):
			$this->status = true;
			if(!$this->stay):
				$this->start();
			endif;
		else:
			$this->direction = '';
			$this->status = false;
		endif;
	}
}

class customer{
	public $cid;
	public $on = false;
	public $arrival_time;
	public $target_floor = 0;
	public $current_floor;
	public $reached_time;
	public $direction;

	function __construct($cid){
		$this->cid = $cid;
		$this->arrival_time = rand(1, TT);
		$this->current_floor = rand(1, FC);
		$this->set_target();
		$this->direction = $this->target_floor > $this->current_floor? 'up': 'down';
	}

	function set_target(){
		while($this->target_floor == $this->current_floor || $this->target_floor == 0){
			$this->target_floor = rand(1, FC);
		}
	}
}

class floor{
	public $status = false;
	public $direction = array('up' => false, 'down' => false);
	public $waiting = array();
	public $num;

	function __construct($num){
		$this->num = $num;
	}

	function cleardirection($direction){
		$this->direction[$direction] = false;
		
	}

	function clear($c){
		unset($this->waiting[$c->cid]);
		$this->check_status();
	}

	function put_waiting($c){
		$this->status = true;
		$this->waiting[$c->cid] = $c;
		$this->direction[$c->direction] = true;
	}

	function check_status(){
		if($this->direction['up'] || $this->direction['down']):
			$this->status = true;
		endif;
			$this->status = false;
	}
}

class main_control{
	public $waiting_floor = array();
	public $up = array();
	public $down = array();

	public function match($distance){
		$tempdis = FC;
		foreach($distance as $dis):
			if($dis['dis'] < $tempdis && $dis['dis']):
				$tempdis = $dis['dis'];
				if($dis['floor']->direction['up']):
						$this->up[$dis['floor']->num] = $dis['ele']->id;
						$name = 'up';
						$fnum = $dis['floor']->num;
				elseif($dis['floor']->direction['down']):
						$this->down[$dis['floor']->num] = $dis['ele']->id;
						$name = 'down';
						$fnum = $dis['floor']->num;
						break;
				else:
						$name = false;
				endif;
			endif;
		endforeach;
		if($name):
			return $this->$name[$fnum];
		endif;
		return false;
	}
}

// intial setting
$floor_count = FC;
$elevator_count = EC;
$customer_count = CC;
$mc = new main_control();
$fl = array();
$el = array();
$cl = array();
$fin = array();

for($i = 1; $i <= $floor_count; $i++):
	$fl[$i] = new floor($i);
endfor;
for($i = 1; $i <= $elevator_count; $i++):
	$el[$i] = new ele($i);
endfor;
for($i = 1; $i <= $customer_count; $i++):
	$cl[$i] = new customer($i);
endfor;

#print_r($el);
#pr_c($cl);
#die();
	echo '<table>';
while(true){
	foreach ($fl as $f) :
		foreach ($cl as $c) :
			if($c->arrival_time == $clock && $f->num == $c->current_floor):
				$f->put_waiting($c);
				unset($cl[$c->cid]);
			endif;
		endforeach;
	endforeach;

	foreach ($fl as $f) :
		foreach ($el as $e) :
			$e->check_floor($clock);
			$e->check_target($clock);
			if($e->stay && $e->current_floor == $f->num):
				foreach ($f->waiting as $f_w) :
					if($e->load($f_w)):
						$f->clear($f_w);
						$f->cleardirection($e->direction);
						unset($xxx);
						$xxx = $e->direction;
						if(isset($mc->$xxx[$f->num])):
							unset($mc->$xxx[$f->num]);
						endif;

						$e->start($clock);
					else:
					endif;
				endforeach;
			endif;
		endforeach;
	endforeach;

	$distance = array();
	foreach ($fl as $f):
		if(count($f->waiting) > 0 ) :
			if(isset($mc->up[$f->num]) || isset($mc->down[$f->num])):
			else:
				foreach ($el as $e) :
					$d = $e->distance($f);
					if($d):
						$distance[] = array("ele" => $e, "floor" => $f, "dis" => $d);
					endif;
				endforeach;
				$matched_eid = $mc->match($distance);
				if($matched_eid):
					$el[$matched_eid]->add_target($f->num);
					$el[$matched_eid]->start($clock);
				endif;
			endif;
		endif;
	endforeach;

	/*
	foreach ($cl as $c) :
		if($c->arrival_time == $clock):
			foreach ($el as $e) :
				if($e->stay && $e->current_floor == $c->current_floor):
					if($c->direction == $e->direction || !$e->direction):
						$e->load($c);
						$c->on = true;
						unset($cl[$c->cid]);
					endif;
				endif;
			endforeach;
			if(!$c->on):
					$fl[$c->current_floor]->put_waiting($c);
					$mc->waiting_floor[$c->current_floor] = $fl[$c->current_floor];
			endif;
		endif;
	endforeach;

	if($mc->waiting_floor):
		foreach ($mc->waiting_floor as $wf) :
			foreach ($el as $e):
				if($e->status && check_same_direction($e, $wf) && !$mc->temp[$fl->num]):
					$e->add_target($fl->num);
					$mc->temp[$fl->num]['direction'] = array($fl->num);
				elseif(!$e->status && !$mc->temp[$fl->num]):
					$e->add_target($fl->num);
					$e->start();
					$mc->temp[$fl->num]['direction'] = array($fl->num, $f['direction']);
				endif;
			endforeach;
		endforeach;
	endif;

	foreach ($el as $e) :
		$e->check_floor($clock);
		if($e->check_target()):
			foreach (@$e->customer_list as $c) :
				if($c->target_floor == $e->current_floor):
					$e->release($c);
					$fin[] = $c;
				endif;
			endforeach;
		endif;
	endforeach;
*/
	$clock++;
	if(count($cl) == 0):
		break;
	endif;


	echo '<tr>';
	echo '<td>'.$clock.'</td>';
	foreach ($el as $e):
		echo '<td>E - '.$e->id.'(';
		foreach($e->customer_list as $c):
		echo "<strong>" .$c->cid . "</strong>[".$c->target_floor."]";
		endforeach;
		echo ')</br>';
		echo 'Now - ' . $e->current_floor . '<br/>';
		echo 'To - ' . implode(',', $e->target_list) .'</br>';
		echo 'Direction :' . $e->direction . '<br/>';
		echo $e->get_status();
		echo '</td>';
	endforeach;
	foreach ($fl as $f):
		echo '<td>F'.$f->num.' - (';
			foreach ($f->waiting as $c):
				echo $c->cid . '['.$c->target_floor.']';
			endforeach;
		echo ')<br/>';
		echo $f->direction['up']? "up":'';
		echo $f->direction['up'] && $f->direction['down']? " & " :"";
		echo $f->direction['down']? "down":'';
		echo'</td>';
	endforeach;
	echo '</tr>';
	?>
	<script type="text/javascript">

	</script>
	<?php
}
	echo '</table>';
echo "finished at " . $clock;


function check_same_direction($e, $f){
	if($f->direction[$e->direction]):
		if($e->direction == 'up' && $f->id < max($e->target_list) && $f->id > $e->current_floor):
			return true;
		elseif($e->direction == 'down' && $f->id > min($e->target_list) && $f->id < $e->current_floor):
			return true;
		endif;
	endif;
	return false;
}

function pr_c($cl){
	echo '<table>';
	echo '<tr><td>CID</td><td>C Floor</td><td>T Floor</td><td>Arrivaled</td></tr>';
	foreach ($cl as $c) :
		echo "<tr><td>{$c->cid}</td><td>{$c->current_floor}</td><td>{$c->target_floor}</td><td>{$c->arrival_time}</td></tr>";
	endforeach;
	echo '</table>';
}
?>