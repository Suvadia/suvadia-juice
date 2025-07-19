<?php


class bhBatteryBankBucket {
		
		function __construct( $index = 0 ){
			
			$this->sorted = false;
			$this->cells = array();
			$this->index = $index;
			$this->mIr = 0;
			$this->mAh = 0;
			$this->mAh_max = 0; 
			$this->mAh_min = 0;
			$this->mAh_dif = 0;
			$this->deviate = 0;
		}
				
		function sort(){
						
			if(!$this->sorted && $this->mAh){
				
				usort($this->cells,function($a, $b){
					// by capacity 
					if( ($r=($b[1]-$a[1])) )
						return $r;
					// by internal resistance
					return ($a[2]-$b[2]);
				});
				$this->sorted  = true;
				$this->mAh_max = $this->cells[0][1];
				$this->mAh_min = $this->cells[count($this->cells)-1][1];
			}
		}
		
					
	
	function findByCapacityRange($mAh, $range){
					
		$found = false;
			
		if($range > 0){
						
			foreach($this->cells as $index => $cell){
								
				$dif = ($cell[1] - $mAh);
				
				if($dif > 0 && $dif <= $range){
																
					if(!$found || $found[1] < $dif)
						$found = array($index, $dif, $cell);
								
					if($dif == $range)
						break;
				}
			}
						
		} else {
			
			
			foreach($this->cells as $index => $cell){
								
				$dif = ($cell[1] - $mAh);
				
				if($dif < 0 && $dif >= $range ){
																
					if(!$found || $found[1] < $dif)
						$found = array($index, $dif, $cell);
								
					if($dif == $range)
						break;
				}
			}
			
		}
				
		return $found;
	}
		
	
	function findByCapacity($mAh, $start = 0){
					
		foreach($this->cells as $index => $cell){
			
			if($index >= $start  &&  $cell[1] == $mAh )
				return array($index, $cell);
		}
		
		return false;
	}
		
}

	class  bhBatteryBankPack {
		
		function __construct(){
			
			$this->cells = array();
			$this->cellbyserial = array();
		}
		
		function check(){
		
			$result = true;
			// sort cells in buckets
			foreach($this->buckets as $bucket){
				
				
				$bucket->sort();
				
				if( ($bucket->mAh_dif = ($this->mAh - $bucket->mAh)) )
					$result = false;
				
				
			}
			//sort buckets low to high
			usort($this->buckets, function($a, $b){
					
				return ($b->mAh_dif - $a->mAh_dif);
			});
										
			return $result;
		}
							
		function exchange($bucket1, $index1, $bucket2, $index2){
			
			$cell1 = $bucket1->cells[$index1];
			$cell2 = $bucket2->cells[$index2];
			
			if( $cell1[1] != $cell2[1] ||  $cell1[2] != $cell2[2] ) {
							
				$bucket1->mAh-= $cell1[1];
				$bucket1->mAh+= $cell2[1];
				$bucket1->mIr-= $cell1[2];
				$bucket1->mIr+= $cell2[2];
				$bucket1->cells[$index1] = $cell2;
				$bucket1->mAh_dif = ($this->mAh - $bucket1->mAh);
				$bucket1->sorted = false;
				$bucket1->sort();
				$bucket1->deviate++;
							
				$bucket2->mAh-= $cell2[1];
				$bucket2->mAh+= $cell1[1];
				$bucket2->mIr-= $cell2[2];
				$bucket2->mIr+= $cell1[2];
				$bucket2->cells[$index2] = $cell1;
				$bucket2->mAh_dif = ($this->mAh - $bucket2->mAh);
				$bucket2->sorted = false;
				$bucket2->sort();
				$bucket2->deviate++;
			}
		}
				
			
		function equalizeCapacity($bucket1, $bucket2){
			
			$result = false;
			
			if($bucket1->index != $bucket2->index){
							
				while($bucket1->mAh_dif > 0){
							
					$range = ($bucket1->mAh_dif - ($bucket1->mAh_dif + $bucket2->mAh_dif));
					$found = false;
					foreach($bucket1->cells as $index => $cell){
						
						if( ($j=$bucket2->findByCapacityRange($cell[1], $range)) !== false){
												 
							if(!$found || $found[0] < $j[1]){
								
								$found = array($j[1], $index, $j[0]);
								if($range == $j[1])
									break;
							}
						}
					}
					
					if($found){
					
						$this->exchange($bucket1, $found[1], $bucket2, $found[2]);
						$result = true;
					} else {
					
						break;
					}
				}
			}
			
			return $result;
		}	
			
		
		function balanceCapacity($bucket1, $bucket2){
								
			if($bucket1->index != $bucket2->index && 
			   ($range=round(($bucket1->mAh_dif - $bucket2->mAh_dif)/2))){
						
				$found = false;
				foreach($bucket1->cells as $index1 => $cell1){

					if( ($j=$bucket2->findByCapacityRange($cell1[1], $range)) !== false){

						if(!$found || $found[0] < $j[1]){

							$found = array($j[1], $index1, $j[0]);
							if($range == $j[1])
								break;
						}
					}
				}

				if($found){

					$this->exchange($bucket1, $found[1], $bucket2, $found[2]);
					return true;
				}
			}
			
			return false;
		}
				
			
		function balanceImpedance($bucket1, $bucket2){
			
			$result = false;
			
			if($bucket1->index != $bucket2->index && $bucket1->mIr != $bucket2->mIr){
								
				if($bucket1->mIr > $bucket2->mIr){
					
					$range = $bucket1->mIr - $bucket2->mIr;
					
					foreach($bucket1->cells as $index => $cell){
						
						$found = array(0);
						while( ($found=$bucket2->findByCapacity($cell[1], $found[0]))){
							
							  	$dif = $cell[2] - $found[1][2];
								if($dif > 0 && $dif <= $range){
																		
									$this->exchange($bucket1, $index, $bucket2, $found[0]);
									if(!($range-=$dif))
										return false;
									
									$result = true;
								}
								$found[0]++;				
						}
						
					}
				}
			}
			
			return $result;
		}
		
		
		function addCells($ListOfCells, $Filters) {
						
			$a = explode(';', $ListOfCells);
			array_pop($a);
			
			list($count, $grade, $mAh_min, $mAh_max, $mIr_min, $mIr_max, $mV_min, $mV_max) = $Filters;
				
			foreach ($a as $data){
				
				$cell = explode(',', $data);
				
				if($cell[5] & BH_BATTERYBANK_PACKCOMMITTED)
					continue;
				
				
				if( !$grade ){
					// capacity
					$mAh = (int)$cell[1];
					if(!$mAh || $mAh < $mAh_min || ($mAh_max && $mAh > $mAh_max))
						continue;
					// resistance					
					$mIr = (int)$cell[2];
					if($mIr < $mIr_min || ($mIr_max && $mIr > $mIr_max))
						continue;
					// voltage
					$mV = (int)$cell[3];
					if($mV < $mIr_min || ($mV_max && $mV > $mV_max))
						continue;
					
										
				} else {
					
					continue;
				}
				
				if(isset($this->cellbyserial[$cell[0]]))
					continue;
							
				$this->cells[] = $cell;
				$this->cellbyserial[$cell[0]] = true;
								
				if($count-- <= 1)
					break;	
			}
			unset($a);
			
			//sort high to low
			usort($this->cells, function($a, $b){
				// by capacity 
				if( ($r=($b[1]-$a[1])) )
					return $r;
				// by internal resistance
				return ($a[2]-$b[2]);
			});
				
			return $count;
		}
		
		
				
		function build($Configuration){
						
			$this->buckets = array();
			$this->surplus = new  bhBatteryBankBucket();
												
			$this->volts  = (int) $Configuration[1];
			$this->stack  = (int) $Configuration[2];
			$mAh = 0;
			$mIr = 0;
			$minCellsRequired = ($this->stack ? ($this->stack * $this->volts) : count($this->cells));
			$c = 0;
			foreach($this->cells as $cell){
				
				$mAh+= $cell[1];
				$mIr+= $cell[2];
				if(++$c == $minCellsRequired)
					break;
			}
			// target mAh  
			$this->mAh = ($this->stack ? (ceil($mAh / $this->volts / 5000) * 5000) : ($Configuration[3] * 1000));
			$this->mIr = ceil($mIr / $this->volts);   
			// create buckets			
			for($i=1; $i <= $this->volts; $i++)
				$this->buckets[] = new bhBatteryBankBucket($i);
			// fill buckets
			$i = 0;
			$step = 0;
			$c = 0;
			foreach($this->cells as &$cell) {
				
				if($c++ < $minCellsRequired) {
					
					$bucket = $this->buckets[$i];
					if( !$step ){
										
						if( ++$i == $this->volts ){
										
							$step = 1;
							$i--;
						}
					}else{
					
						if( $i-- ==  0){
						
							$i++;
							$step = 0;
							// auto
							if(!$this->stack && $this->buckets[0]->mAh >=  $this->mAh){
								
								$minCellsRequired = $c;
							}
						}
					}
				} else {
					
					$bucket = $this->surplus;
				}
				
				$cell[7] = $bucket->index;
				$bucket->cells[] = &$cell;
				$bucket->mIr+= $cell[2];
				$bucket->mAh+= $cell[1];
				
				
			}
			unset($cell);
			
			$this->surplus->sort();
			
			// equalize		
			do {
				
				$done = true;
				for($i = 0; $i < $this->volts; $i++){
					
					while(!$this->check() && $this->equalizeCapacity($this->buckets[$i], $this->buckets[$this->volts - 1]))
						$done = false;
				}
			
			}while(!$done && !$this->check());
			
			// balance	
			$done = 0;
			while(!$this->check() && $done++ < $minCellsRequired) {
								
				for($j = 0; $j < $this->volts; $j++)									
					for($i = 0; $i < $this->volts; $i++)
						$this->balanceCapacity($this->buckets[$i], $this->buckets[$j]);
			}
			
			
			// impedance
			do {
				
				$done = true;				
				for($j = 0; $j < $this->volts; $j++)
					for($i = 0; $i < $this->volts; $i++)
						if($this->balanceImpedance($this->buckets[$i], $this->buckets[$j]))
							$done = false;
			} while(!$done);
			/*
			
			foreach($this->buckets as $bucket){
						
				$range = $this->mIr - $bucket->mIr;
				foreach($bucket->cells as $index => $cell){
						
					$found = array(0);
					while( ($found=$this->surplus->findByCapacity($cell[1], $found[0]))){
							
						$dif = $found[1][2] -  $cell[2];
						if($dif > 0 && $dif <= $range){
														
							$this->exchange($bucket, $index, $this->surplus, $found[0]);
							if(!($range-=$dif))
								break;
						}
						
						$found[0]++;		
					}
				}
			}*/
			
			// sort buckets Ir low to high
			usort($this->buckets, 
				  function($a, $b){
					
					return ($a->mIr - $b->mIr);
			});
			
			$ref = $this->mIr; 
			foreach($this->buckets as $bucket){
				
			//	$bucket->mIr-=$ref; 
			}
					
		}
	}
	


?>