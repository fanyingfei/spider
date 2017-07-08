<?php

class basis{
    const RESUME_INIT = 0;
    const RESUME_RUN = 1;
    const RESUME_SUC = 2;
    const RESUME_ERROR = 3;//抓取异常
    const RESUME_PARAM = 4;//非法参数
    const RESUME_BODY = 5;//内容有误
    const RESUME_NOT_WORKEPX = 6;//没有工作经验

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

    const CONDITION_IS_FULL = 1;//搜索条件抓完
    const CONDITION_NOT_FULL = 0;//搜索条件没抓完


    public  function __construct()
    {

    }
}