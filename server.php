<?php
error_reporting(E_ALL);
define("NL", "\n\r");

class Server {
	private $events = array();
	private $sockets = array();
	
	
	public function Server($port) 
	{
		$this->port = $port;
		$this->serverSocket = socket_create_listen($this->port);
		$this->sockets[] = $this->serverSocket;
	}
	
	public function log($msg) 
	{
		echo trim($msg).NL;
	}
	
	public function getSockets() 
	{
		$sockets = $this->sockets;
		return $sockets;
	}
	
	public function addEvent($eventName, $callback) 
	{
		if(!isset($this->events[$eventName]) || !$this->events[$eventName] ) 
			$this->events[$eventName] = array();
			
		$this->events[$eventName][] = $callback;
	}
	
	private function fireEvent($eventName, $params = array()) 
	{
		if(!isset($this->events[$eventName])  || !$this->events[$eventName] ) 
			$this->events[$eventName] = array();
			
		foreach($this->events[$eventName] as $event) {
			$event($params);
		}
	}
	
	public function waitEventFromSockets() 
	{
		$clientSockets = $this->sockets;		
		if (socket_select($clientSockets, $write = NULL, $except = NULL, 0) < 1) return;
		
		if(in_array($this->serverSocket, $clientSockets)) {
			$this->sockets[] = $clientSocket = $this->acceptClient();
			$this->fireEvent('onClientConnected', array('clientSocket'=>$clientSocket));
			$key = array_search($this->serverSocket, $clientSockets);
			unset($clientSockets[$key]);
		}
		
		foreach($clientSockets as $clientSocket) {
			$data = @socket_read($clientSocket, 1024, PHP_NORMAL_READ);
			if ($data === false) {
                $key = array_search($clientSocket, $this->sockets);
                unset($this->sockets[$key]);
				$this->fireEvent('onClientDisconnected', array());
            } else {
				$this->fireEvent('onNewData', array('clientSocket'=>$clientSocket,'data'=>trim($data)));
			}
		}		
	}
	
	private function acceptClient()
	{		
		if (($clientSocket = socket_accept($this->serverSocket)) === false) {
			$msg = "socket_accept() failed: reason: " . socket_strerror(socket_last_error($this->serverSocket));			
		}
		return $clientSocket;
	}

	public function run() 
	{		
		register_tick_function(array($this, 'waitEventFromSockets'));
		declare( ticks=1 ){
			while( true ){
				sleep(1);
			} 
		}
	}
}

$server = new Server(9001);
$server->addEvent("onClientConnected", function($params) use ($server){
	extract($params);
	$server->log("Client connected! Total: ".(sizeOf($server->getSockets())-1));
	socket_write($clientSocket, "Welcome\r\n");
});

$server->addEvent('onNewData', function($params) use ($server)  {
	extract($params);
	foreach($server->getSockets() as $socket) {	
		if ($server->serverSocket == $socket || $clientSocket== $socket)
			continue;
		if(trim($data)!="") {
			socket_write($socket, $data.NL);
		}
	}
});

$server->addEvent('onClientDisconnected', function() use ($server) {
	$server->log("Client disconnected! Total: ".(sizeOf($server->getSockets())-1));
});
$server->run();