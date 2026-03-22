<?php
/*
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */
namespace PointStart\Attributes{

use Attribute;

    enum HttpMethod: string {
        case GET     = 'GET';
        case POST    = 'POST';
    }

#[Attribute(Attribute::TARGET_METHOD)]
    class Route{
        public function __construct(
            public string $path,
            public HttpMethod $httpMethod = HttpMethod::GET,
            public bool $csrfExempt = false
        ) {}
    }

}
?>