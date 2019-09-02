<?php

namespace app\model;

use think\Model;

class User extends Model
{
    public function getDb(){
        var_dump(self::$db);
    }
}
