<?php

namespace app\listener;

class ShowAppInit
{
    public function handle($event)
    {
        echo "App 初始化啦<br>";
    }    
}
