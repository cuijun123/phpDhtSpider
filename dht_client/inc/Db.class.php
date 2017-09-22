<?php
	class Db{
		private static $conn;
		private static $rsid;
		private static $query_count = 0;
		public static $config=array(

		);
		
		protected static function _init_mysql($config = array()){
			if(empty(self::$config))
				exit("extension redis is not installed".PHP_EOL);
			if (!self::$conn){
				self::$conn = mysqli_connect(self::$config['host'], self::$config['user'], self::$config['pass'], self::$config['name'], 3306);
				if (empty(self::$conn))
					echo "Connect MySql Error!".PHP_EOL;
				else{
					mysqli_query(self::$conn, " SET character_set_connection=utf8, character_set_results=utf8, character_set_client=binary, sql_mode='' ");
				}
			}
		}
		
		public static function query($sql){
			
			$sql = trim($sql);

			// 初始化数据库
			self::_init_mysql();

			self::$rsid = mysqli_query(@self::$conn,$sql);
			if (self::$rsid === false){
				// 不要每次都ping，浪费流量浪费性能，执行出错了才重新连掿
				$errno = mysqli_errno(self::$conn);
				if ($errno == 2013 || $errno == 2006) 
				{
					@mysqli_close(self::$conn);
					self::$conn = null;
					return self::query($sql);
				}

				echo "Query SQL: ".$sql."\n";
				echo "Error SQL: ErrorNo --- ".mysqli_errno(self::$conn).' --- Error --- '.mysqli_error(self::$conn)."\n";
				$backtrace = debug_backtrace();
				array_shift($backtrace);
				$narr = array('class', 'type', 'function', 'file', 'line');
				$err = "debug_backtrace：\n";
				foreach($backtrace as $i => $l)
				{
					foreach($narr as $k)
					{
						if( !isset($l[$k]) ) $l[$k] = '';
					}
					$err .= "[$i] in function {$l['class']}{$l['type']}{$l['function']} ";
					if($l['file']) $err .= " in {$l['file']} ";
					if($l['line']) $err .= " on line {$l['line']} ";
					$err .= "\n\n";
				}
				echo $err;
				return false;
			}else{
				self::$query_count++;
				return self::$rsid;
			}
		}
		
		public static function get_one($sql, $func = '')
		{
			if (!preg_match("/limit/i", $sql))
			{
				$sql = preg_replace("/[,;]$/i", '', trim($sql)) . " limit 1 ";
			}
			$rsid = self::query($sql);
			if ($rsid === false)
			{
				return;
			}
			$row = mysqli_fetch_array($rsid, MYSQLI_ASSOC);
			mysqli_free_result($rsid);
			if (!empty($func))
			{
				return call_user_func($func, $row);
			}
            return $row;
		}
		
		public static function get_all($sql, $func = '')
		{
			$rsid = self::query($sql);
			while ( $row = mysqli_fetch_array($rsid, MYSQLI_ASSOC) )
			{
				$rows[] = $row;
			}
			mysqli_free_result($rsid);
			if (!empty($func))
			{
				return call_user_func($func, $rows);
			}
			return empty($rows) ? false : $rows;
		}
		
		public static function insert_id()
		{
			return mysqli_insert_id(self::$conn);
		}
		
		public static function affected_rows()
		{
			return mysqli_affected_rows(self::$conn);
		}
		
		public static function insert($table = '', $data = null, $return_sql = false)
		{
			$items_sql = $values_sql = "";
			foreach ($data as $k => $v)
			{
				$v = stripslashes($v);
				$v = addslashes($v);
				$items_sql .= "`$k`,";
				$values_sql .= "\"$v\",";
			}
			$sql = "Insert Ignore Into `{$table}` (" . substr($items_sql, 0, -1) . ") Values (" . substr($values_sql, 0, -1) . ")";
			if ($return_sql) 
			{
				return $sql;
			}
			else 
			{
				if (self::query($sql))
				{
					return mysqli_insert_id(self::$conn);
				}
				else 
				{
					return false;
				}
			}
		}
		
		public static function insert_batch($table = '', $set = NULL, $return_sql = FALSE) 
		{
			if (empty($table) || is_null($set)) 
			{
				return false;
			}
			$set = self::strsafe($set);

			$keys_sql = $vals_sql = array();
			$flag = true;
			foreach ($set as $i=>$fields) 
			{
				$vals = array();
				foreach ($fields as $k => $v)
				{
					if ($flag) 
					{
						$keys_sql[] = "`$k`";
					}
					$vals[] = "\"$v\"";
				}
				if($i == 0)
					$flag = false;
				$vals_sql[] = implode(",", $vals);
			}

			$sql = "Insert Ignore Into `{$table}`(".implode(", ", $keys_sql).") Values (".implode("), (", $vals_sql).")";

			if ($return_sql) return $sql;
			
			$rt = self::query($sql);
			$insert_id = self::insert_id();
			$return = empty($insert_id) ? $rt : $insert_id;
			return $return;
		}
		
		public static function update_batch($table = '', $set = NULL, $index = NULL, $where = NULL, $return_sql = FALSE) 
		{
			//递归过滤防注兿
			$set = self::strsafe($set);
			$ids = array();
			$where = ($where != '' AND count($where) >=1) ? implode(" ", $where).' AND ' : '';

			foreach ($set as $val)
			{
				// 去重
				$key = md5($val[$index]);
				$ids[$key] = $val[$index];

				foreach (array_keys($val) as $field)
				{
					if ($field != $index)
					{
						$final[$field][$key] =  'When `'.$index.'` = "'.$val[$index].'" Then "'.$val[$field].'"';
					}
				}
			}

			$sql = "Update `".$table."` Set ";
			$cases = '';

			foreach ($final as $k => $v)
			{
				$cases .= '`'.$k.'` = Case '."\n";
				foreach ($v as $row)
				{
					$cases .= $row."\n";
				}

				$cases .= 'Else `'.$k.'` End, ';
			}

			$sql .= substr($cases, 0, -2);

			$sql .= ' Where '.$where.$index.' In ("'.implode('","', $ids).'")';

			if ($return_sql) return $sql;
			
			$rt = self::query($sql);
			$insert_id = self::affected_rows();
			$return = empty($affected_rows) ? $rt : $affected_rows;
			return $return;
		}

		public static function update($table = '', $data = array(), $where = null, $return_sql = false)
		{
			$sql = "UPDATE `{$table}` SET ";
			foreach ($data as $k => $v)
			{
				$v = stripslashes($v);
				$v = addslashes($v);
				$sql .= "`{$k}` = \"{$v}\",";
			}
			if (!is_array($where))
			{
				$where = array($where);
			}
			// 删除空字殿不然array("")会成为WHERE
			foreach ($where as $k => $v)
			{
				if (empty($v))
				{
					unset($where[$k]);
				}
			}
			$where = empty($where) ? "" : " Where " . implode(" And ", $where);
			$sql = substr($sql, 0, -1) . $where;
			
			if ($return_sql) 
			{
				return $sql;
			}
			else 
			{
				if (self::query($sql))
				{
					return mysqli_affected_rows(self::$conn);
				}
				else 
				{
					return false;
				}
			}
		}

		public static function ping()
		{
			if (!mysqli_ping(self::$conn))
			{
				@mysqli_close(self::$conn);
				self::_init_mysql();
			}
		}

		public static function strsafe($array)
		{
			$arrays = array();
			if(is_array($array)===true)
			{
				foreach ($array as $key => $val)
				{
					$arrays[$key] = self::strsafe($val);
				}
			}
			else 
			{
				$array = stripslashes($array);
				$array = addslashes($array);
				
			}
			return $array;
		}
	}