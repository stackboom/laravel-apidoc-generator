<?php


namespace Mpociot\ApiDoc\Tools\ResponseStrategies;


use Illuminate\Routing\Route;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Mpociot\Reflection\DocBlock\Tag;

class ResponderStrategy
{
    /**
     * @var Route $route
     */
    private $route;

    private $routeProps;

    public function __invoke(Route $route, array $tags, array $routeProps)
    {
        $this->route = $route;
        $this->routeProps = $routeProps;
        return $this->getResponderResponses($tags);
    }

    /**
     * @param array $tags
     * @return array|null
     */
    private function getResponderResponses(array $tags)
    {
        $responderTags = array_values(
            array_filter($tags, function ($tag) {
                return $tag instanceof Tag
                    && strtolower($tag->getName()) === 'see'
                    && Str::endsWith($tag->getContent(),['//Responder']);
            })
        );

        if (empty($responderTags)) {
            return;
        }

        /**
         * @var Tag[] $responderTags
         */
        if(count($responderTags)==1){
            $responders = explode('|',$responderTags[0]->getContent());
        }else {
            $responders = [];
            foreach ($responderTags as $responderTag){
                $responders[] = $responderTag->getContent();
            }
        }

        return array_filter(array_map([$this,'resolveResponder'],$responders));
    }

    /**
     * @param $responder
     * @throws \ReflectionException
     */
    private function resolveResponder($responder){
        $responder = str_replace('//Responder','',$responder);
        list($class,$action) = explode('::',trim($responder));
        $class = $this->guessClass($class,config('apidoc.responder_classes',[]));
        $action = trim($action,'()');
        if($class)
            $result = call_user_func([$class,$action]);
        if(isset($result) && $result instanceof \Illuminate\Contracts\Support\Responsable){
            return $result->toResponse(app('request'));
        }
    }

    /**
     * @param $name
     * @param $fullname
     * @return string|null
     */
    private function guessClass($name, $fullname){
        if(isset($fullname[$name]) && class_exists($fullname[$name])){
            return $fullname[$name];
        }
        if(app()->has($name)){
            return app($name);
        }
    }

    /**
     * Parse the class name and format according to the root namespace.
     *
     * @param  string  $name
     * @return string
     */
    protected function qualifyClass($name)
    {
        $name = ltrim($name, '\\/');

        $rootNamespace = app()->getNamespace();

        if (Str::startsWith($name, $rootNamespace)) {
            return $name;
        }

        $name = str_replace('/', '\\', $name);

        return $this->qualifyClass(trim($rootNamespace, '\\').'\\'.$name);
    }
}