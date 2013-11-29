<?php
/*
$host = "localhost";
$port = 9001;
if (($clientSocket = socket_create(AF_INET, SOCK_STREAM, 0)) === false) {
			echo "socket_create() failed: reason: " . socket_strerror(socket_last_error()) . "\n";
		}
		socket_connect($clientSocket, $host, $port);
	
	socket_write($clientSocket, "Hello World!\n\r");
	sleep(2);
exit;
*/
class Client {
	
	private $sockets = array();
	private $events = array();
	
	public function Client($host, $port) 
	{
		if (($clientSocket = socket_create(AF_INET, SOCK_STREAM, 0)) === false) {
			echo "socket_create() failed: reason: " . socket_strerror(socket_last_error()) . "\n";
		}
		socket_connect($clientSocket, $host, $port);
		$this->sockets[] = $clientSocket;
	}
	
	public function addEvent($eventName, $callback) 
	{
		if(!isset($this->events[$eventName])  || !$this->events[$eventName] ) 
			$this->events[$eventName] = array();
			
		$this->events[$eventName][] = $callback;
	}
	
	public function fireEvent($eventName, $params = array()) 
	{
		if(!isset($this->events[$eventName])  || !$this->events[$eventName] ) 
			$this->events[$eventName] = array();
			
		foreach($this->events[$eventName] as $event) {
			$event($params);
		}
	}
	
	public function waitEventFromSockets()
	{
		$readSockets = $this->sockets;
		
		if (socket_select($readSockets, $writeSockets = NULL, $except = NULL, 0) < 1) return;
		
		
		foreach($readSockets as $socket) {
			$data = @socket_read($socket, 1024, PHP_NORMAL_READ);
			if ($data === false) {
                $key = array_search($socket, $this->sockets);
                unset($this->sockets[$key]);
				$this->fireEvent('onClientDisconnected', array());
            } else {
				$this->fireEvent('onNewData', array('clientSocket'=>$socket,'data'=>trim($data)));
			}
		}
	}
	
	function non_block_read($fd, &$data) 
	{
		$read = array($fd);
		$write = null;
		$except = null;
		$result = stream_select($read, $write, $except, 0);
		if($result === false) throw new Exception('stream_select failed');
		if($result === 0) return false;
		$data = stream_get_line($fd, 1);
		return true;
	}

	public function waitForMessage() 
	{
		$x = "";
		if($this->non_block_read(STDIN, $x)) {
			$clientSocket = $this->sockets[0];
			socket_write($clientSocket, $x."\r\n");
		}	
	}
	
	public function run() 
	{		
		register_tick_function(array($this, 'waitEventFromSockets'));
		//register_tick_function(array($this, 'waitForMessage'));
		declare( ticks=1 ){
			while( true ){
				usleep(1);
			} 
		}
	}

}


$client = new Client("localhost", 9001);
$client->addEvent('onNewData', function($params) use ($client)  {
	extract($params);
	echo $data;
});


$client->run();

/*

while(true) {
	//Blocking function
	$msg  = socket_read($clientSocket, 2048, PHP_NORMAL_READ);
	
	if(!$msg) break;
	
	if(trim($msg)!="") {
		echo "Server: $msg\r\n";
		
		
		echo "Enter message: ";
		$msg = trim(fgets(STDIN))."\r\n";
		socket_write($clientSocket, $msg, strlen($msg));
	}
}
*/


