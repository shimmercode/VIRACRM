<?php
foreach (['REQUEST_URI','METHOD','HTTP_HOST','VIRA_ROOT','COOKIE','POST_BODY'] as $k) {
    printf("%-12s getenv=%-22s _SERVER=%s\n", $k,
        var_export(getenv($k), true),
        var_export($_SERVER[$k] ?? null, true));
}
