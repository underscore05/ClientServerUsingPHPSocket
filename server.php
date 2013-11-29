<?php
error_reporting(E_ALL);
define("NL", "\n\r");

class Event{
	
	protected $events = array();
	
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
	
	private function getSocketError()
	{		
		return socket_strerror(socket_last_error($socket));
	}

	public function log($msg) 
	{
		echo trim($msg).NL;
	}
	
}

class Server extends Socket{
	
	private $sockets = array();
	
	public function Server($port) 
	{
		$this->port = $port;
		$this->serverSocket = socket_create_listen($this->port);
		$this->sockets[] = $this->serverSocket;
	}
	
	public function getSockets() 
	{
		$sockets = $this->sockets;
		return $sockets;
	}
	
	public function waitEventFromSockets() 
	{
		$clientSockets = $this->sockets;		
		if (socket_select($clientSockets, $write = NULL, $except = NULL, 0) < 1) return;
		
		if(in_array($this->serverSocket, $clientSockets)) {
			$this->sockets[] = $clientSocket = socket_accept($this->serverSocket);
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

$server->addEvent('onClientDisconnected', function() use ($server) {
	$server->log("Client disconnected! Total: ".(sizeOf($server->getSockets())-1));
});

$server->addEvent('onNewData', function($params) use ($server)  {
	extract($params);
	foreach($server->getSockets() as $socket) {	
		if ($server->serverSocket == $socket || $clientSocket== $socket)
			continue;
		if(trim($data)!="") {
			socket_write($socket, trim($data).NL);
		}
	}
});

$server->run();