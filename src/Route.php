<?php
/**
 * Clover Route  PHP极速路由
 *
 * @author Donghaichen [<chendongahai888@gmailc.com>]
 * @todo 命名路由,路由群组,路由中间件
 */
namespace Clovers\Route;
use Exception;

class Route
{
    const namespace = '\App\Controllers';
    private $rootes_tree = null;
    private static $allowed_methods = ['get', 'post', 'put','patch', 'delete','head', 'options', 'any'];
    public static $allowed_routes = [];

    /**
     * 添加一个静态路由方法
     * @param $method
     * @param $arguments
     * @return array
     * @throws Exception
     */
    public static function __callStatic($method, $arguments)
    {
        $method = (array)$method;
        $action = $arguments[1];
        $route  = $arguments[0];
        if (array_diff($method, self::$allowed_methods)) {
            throw new Exception('Method:' . $method . ' is not valid');
        }
        if (array_search('any', $method) !== false) {
            $methods = [
                'get' => $action,
                'post' => $action,
                'put' => $action,
                'patch' => $action,
                'delete' => $action,
                'head' => $action,
                'options' => $action
            ];
        } else {
            foreach ($method as $v) {
                $methods[$v] = $action;
            }
        }
        self::$allowed_routes[] = ['route' => $route, 'method' => $methods];
    }

    /**
     * 从路由映射表中匹配路由
     * @param $method
     * @param $uri
     * @return array
     * @throws Exception
     */
    public function match($method, $uri)
    {
        if ($this->rootes_tree == null) {
            $this->rootes_tree = $this->parseRoutes(self::$allowed_routes);
        }
        $search = $this->normalize($uri);
        $node = $this->rootes_tree;
        $params = [];
        //loop every segment in request url, compare it, collect parameters names and values
        foreach ($search as $v) {
            if (isset($node[$v['use']])) {
                $node = $node[$v['use']];
            } elseif (isset($node['*'])) {
                $node = $node['*'];
                $params[$node['name']] = $v['name'];
            } elseif (isset($node['?'])) {
                $node = $node['?'];
                $params[$node['name']] = $v['name'];
            }else{
                return false;
            }
        }
        while (!isset($node['exec']) && isset($node['?'])) {
            $node = $node['?'];
        }
        if (isset($node['exec'])) {
            if (!isset($node['exec']['method'][$method]) && !isset($node['exec']['method']['any'])) {
                throw new Exception('Method: ' . $method . ' is not allowed for this route');
            }
            return [
                'route' => $node['exec']['route'],
                'method' => $method,
                'action' => $node['exec']['method'][$method],
                'params' => $params
            ];
        }else{
            return false;
        }

    }

    /**
     * 运行路由
     * @return string
     */
    public function run()
    {
        $method = strtolower($_SERVER['REQUEST_METHOD']);
        $uri = $this->uri();
        $match = $this->match($method, $uri);
        if(!$match) return $this->runController('BaseController@httpNotFound');
        $action = $match['action'];
        $params = $match['params'];
        return is_string($action) ? $this->runController($action, $params) : $this->runCallable($action, $params);
    }

    /**
     * 运行控制器方法
     * @param $action
     * @param $request
     * @return object
     */
    private function runController($action, $request = [] )
    {
        $countroller = explode("@", $action);
        $class = self::namespace . '\\' . $countroller[0];
        call_user_func_array([new $class, $countroller[1]], $request);
    }

    /**
     * 运行匿名回调函数
     * @param $action
     * @param $request
     * @return object
     */
    private function runCallable($action, $request = null)
    {
        call_user_func($action, $request);
    }

    /**
     * 处理URI,方便在ROUTES文件中匹配
     * @param $uri
     * @return string
     */
    private static function uri($uri = null){
        $uri = is_null($uri) ? urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)) : $uri;
        $uri = str_replace("//", '/', $uri);
        $uri = str_replace($_SERVER['SCRIPT_NAME'], '', $uri);
        $uri = $uri !== '/' ? trim($uri, '/') : $uri;
        return empty($uri) ? '/' : $uri;
    }

    /**
     * 序列化URI
     * @param $route
     * @return array
     */
    protected function normalize($route)
    {
        //make sure that all urls have the same structure
        if (mb_substr($route, 0, 1) != '/') {
            $route = '/' . $route;
        }
        if (mb_substr($route, -1, 1) == '/') {
            $route = substr($route, 0, -1);
        }
        $result = explode('/', $route);
        $result[0] = '/';
        $ret = [];
        foreach ($result as $v) {
            if (!$v) {
                continue;
            }
            if (strpos($v, '?}') !== false) {
                $ret[] = ['name' => explode('?}', mb_substr($v, 1))[0], 'use' => '?'];
            } elseif (strpos($v, '}') !== false) {
                $ret[] = ['name' => explode('}', mb_substr($v, 1))[0], 'use' => '*'];
            } else {
                $ret[] = ['name' => $v, 'use' => $v];
            }
        }
        return $ret;
    }
    /**
     * 创建路由映射表
     * @param $routes
     * @return array
     */
    protected function parseRoutes($routes)
    {
        $tree = [];
        foreach ($routes as $route) {
            $node = &$tree;
            foreach ($this->normalize($route['route']) as $segment) {
                if (!isset($node[$segment['use']])) {
                    $node[$segment['use']] = ['name' => $segment['name']];
                }
                $node = &$node[$segment['use']];
            }
            if (isset($node['exec'])) {
                $node['exec']['method'] = array_merge($node['exec']['method'], $route['method']);
            } else {
                $node['exec'] = $route;
            }
            $node['name'] = $segment['name'];
        }
        return $tree;
    }
}
