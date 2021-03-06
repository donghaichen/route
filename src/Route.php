<?php
/**
 * Clover Route  PHP极速路由
 *
 * @author Donghaichen [<chendongahai888@gmailc.com>]
 * @todo 命名路由,路由群组,路由中间件,路由参数验证
 */
namespace Clovers\Route;
use Exception;

class Route
{
    private static $namespace = '\App\Controllers';
    private $rootes_tree = null;
    private static $allowed_methods = [
        'get',
        'post',
        'put',
        'patch',
        'delete',
        'head',
        'options',
        'trace',
        'connect',
        'any'
    ];

    // REST路由操作方法定义
    private static $rest = [
        'index'  => ['GET', '', 'index'],
        'create' => ['GET', '/create', 'create'],
        'edit'   => ['GET', '/:id/edit', 'edit'],
        'read'   => ['GET', '/:id', 'read'],
        'save'   => ['POST', '', 'save'],
        'update' => ['PUT', '/:id', 'update'],
        'delete' => ['DELETE', '/:id', 'delete'],
    ];

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
        if (!in_array($method, self::$allowed_methods))
        {
            throw new \BadMethodCallException('Method:' . strtoupper($method) . ' is not valid');
        }else
        {
            $method = (array)$method;
            $action = $arguments[1];
            $route  = $arguments[0];
        }
        if (array_search('any', $method) !== false)
        {
            $methods = [
                'get'       => $action,
                'post'      => $action,
                'put'       => $action,
                'patch'     => $action,
                'delete'    => $action,
                'head'      => $action,
                'options'   => $action,
                'trace'     => $action,
                'connect'   => $action,
            ];
        } else {
            foreach ($method as $v) {
                $methods[$v] = $action;
            }
        }
        self::$allowed_routes[] = [
            'route' => $route,
            'method' => $methods
        ];
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
        $match = $this->match($method, $uri); //http://localhost:8888/user/0/test/5 类似这样值为0则匹配失败 待优化
        if(!$match) return $this->runController('BaseController@httpNotFound');
        $action = $match['action'];
        $params = $match['params'];
        return is_string($action) ? $this->runController($action, $params) : $this->runCallable($action, $params);
    }

    /**
     * 运行控制器方法
     * @param $action
     * @param $request (注入请求参数)
     * @return object
     */
    private function runController($action, $request = [] )
    {
        $countroller = explode("@", $action);
        $class = self::$namespace . '\\' . $countroller[0];
        if(!$request){
            call_user_func_array([new $class, $countroller[1]], ['request' => $request]);
        }else{
            $class = new \ReflectionClass($class); // 建立反射类
            $instance  = $class->newInstanceArgs(['request' => $request]); // 实例化类
            $method = $class->getmethod($countroller[1]); // 获取类中的方法
            $method->invokeArgs($instance, ['request' => $request]);//调用方法，通过数组传参数
        }
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
        $uri = str_replace("//", DS, $uri);
        $uri = str_replace($_SERVER['SCRIPT_NAME'], '', $uri);
        $uri = $uri !== DS ? trim($uri, DS) : $uri;
        return empty($uri) ? DS : $uri;
    }

    /**
     * 序列化URI
     * @param $route
     * @return array
     */
    protected function normalize($route)
    {
        //make sure that all urls have the same structure
        if (mb_substr($route, 0, 1) != DS) {
            $route = DS . $route;
        }
        if (mb_substr($route, -1, 1) == DS) {
            $route = substr($route, 0, -1);
        }
        $result = explode(DS, $route);
        $result[0] = DS;
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


    /**
     * 注册资源路由
     * @access public
     * @param string    $rule 路由规则
     * @param string    $route 路由地址
     * @param array     $option 路由参数
     * @param array     $pattern 变量规则
     * @return void
     */
    public static function resource($rule, $route = '', $option = [], $pattern = [])
    {
        if (is_array($rule)) {
            foreach ($rule as $key => $val) {
                if (is_array($val)) {
                    list($val, $option, $pattern) = array_pad($val, 3, []);
                }
                self::resource($key, $val, $option, $pattern);
            }
        } else {
            if (strpos($rule, '.')) {
                // 注册嵌套资源路由
                $array = explode('.', $rule);
                $last  = array_pop($array);
                $item  = [];
                foreach ($array as $val) {
                    $item[] = $val . '/:' . (isset($option['var'][$val]) ? $option['var'][$val] : $val . '_id');
                }
                $rule = implode('/', $item) . '/' . $last;
            }
            // 注册资源路由
            foreach (self::$rest as $key => $val) {
                if ((isset($option['only']) && !in_array($key, $option['only']))
                    || (isset($option['except']) && in_array($key, $option['except']))) {
                    continue;
                }
                if (isset($last) && strpos($val[1], ':id') && isset($option['var'][$last])) {
                    $val[1] = str_replace(':id', ':' . $option['var'][$last], $val[1]);
                } elseif (strpos($val[1], ':id') && isset($option['var'][$rule])) {
                    $val[1] = str_replace(':id', ':' . $option['var'][$rule], $val[1]);
                }
                $item = ltrim($rule . $val[1], '/');
                self::run($item . '$', $route . '/' . $val[2], $val[0], $option, $pattern);
            }
        }
    }
}
