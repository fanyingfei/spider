<?php

class basis{
    const CONFIG_SUC = 1;

    const RESUME_INIT = 0;
    const RESUME_RUN = 1;
    const RESUME_SUC = 2;
    const RESUME_ERROR = 3;//参数异常
    const RESUME_PARAM = 4;//非法参数
    const RESUME_BODY = 5;//内容有误

    const ACCOUNT_INIT = 0; //初始
    const ACCOUNT_RUN = 1; //正在使用
    const ACCOUNT_LOGIN = 2; //重新登录
    const ACCOUNT_ERROR = 3; //账号错误
    const ACCOUNT_PHONE = 4; //异地短信验证

    const CONDITION_INIT = 0;
    const CONDITION_RUN = 1;
    const CONDITION_SUC = 2;
    const CONDITION_ERROR = 3;//抓取异常
    const CONDITION_NULL = 4;//无搜索结果


    public  function __construct()
    {

    }
}