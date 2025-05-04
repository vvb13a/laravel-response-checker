<?php

namespace Vvb13a\LaravelResponseChecker\Enums;

enum FindingLevel: string
{
    case SUCCESS = 'success';
    case INFO = 'info';
    case WARNING = 'warning';
    case ERROR = 'error';
}
