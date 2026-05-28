<?php
namespace exface\UI5Facade\Facades\Middleware;

use exface\Core\Exceptions\Facades\FacadeRuntimeError;
use exface\Core\Interfaces\Exceptions\ExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use exface\Core\Interfaces\Facades\HttpFacadeInterface;
use GuzzleHttp\Psr7\Response;
use exface\Core\DataTypes\StringDataType;
use exface\UI5Facade\Exceptions\UI5RouteInvalidException;
use exface\UI5Facade\Facades\UI5Facade;
use exface\Core\Interfaces\Tasks\HttpTaskInterface;
use exface\Core\Facades\AbstractHttpFacade\Middleware\Traits\TaskRequestTrait;
use exface\Core\Exceptions\Facades\FacadeOutputError;

/**
 * This PSR-15 middleware routes requests to components of a UI5 webapp.
 * 
 * @author Andrej Kabachnik
 *
 */
class UI5WebappRouter implements MiddlewareInterface
{
    use TaskRequestTrait;
    
    private $facade = null;
    
    private $taskAttributeName = null;
    
    private $webappRoot = null;
    
    private $webapp = null;
    
    /**
     * 
     * @param HttpFacadeInterface $facade
     */
    public function __construct(UI5Facade $facade, string $webappRoot = '/webapps/', string $taskAttributeName = 'task')
    {
        $this->facade = $facade;
        $this->taskAttributeName = $taskAttributeName;
        $this->webappRoot = $webappRoot;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \Psr\Http\Server\MiddlewareInterface::process()
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();
        if (($webappRoute = StringDataType::substringAfter($path, $this->webappRoot)) !== false) {
            try {
                return $this->resolve($webappRoute, $this->getTask($request, $this->taskAttributeName, $this->facade));
            } catch (\Throwable $e) {
                $this->facade->getWorkbench()->getLogger()->logException(new FacadeOutputError('Error in UI5 router: ' . $e->getMessage(), null, $e));
                return $this->facade->createResponseFromError($e, $request);
            }
        }
        return $handler->handle($request);
    }
    
    /**
     * 
     * @param string $route
     * @param HttpTaskInterface $task
     * @return ResponseInterface
     */
    protected function resolve(string $route, HttpTaskInterface $task = null) : ResponseInterface
    {
        $target = StringDataType::substringAfter($route, '/');
        $appId = StringDataType::substringBefore($route, '/');
        
        $webapp = $this->facade->initWebapp($appId);
        try {
            $body = $webapp->get($target, $task);
        } catch (UI5RouteInvalidException $e) {
            return new Response(404, [], $e->getMessage());
        }
        
        $config = $this->facade->getConfig();
        $headers = array_merge(
            array_filter($config->getOption('FACADE.HEADERS.COMMON')->toArray()),
            array_filter($config->getOption('FACADE.HEADERS.AJAX')->toArray())
        );
        
        $type = pathinfo($target, PATHINFO_EXTENSION);
        switch (strtolower($type)) {
            case 'json':
                $headers['Content-type'] = 'application/json;charset=utf-8';
                return new Response(200, $headers, $body);
            case 'js':
                $headers['Content-type'] = 'application/javascript';
                return new Response(200, $headers, $body);                
        }
        
        return new Response(200, $headers, $body);
    }
}