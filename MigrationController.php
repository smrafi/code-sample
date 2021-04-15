<?php

namespace App\Http\Controllers\v1;

use App\Services\Migration\MigrationRequest;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Facades\App\Services\Migration\MigrationProcessor;

/**
 * Class MenuItemController
 * @ApiDocumentor (
 *     ignore: true
 * )
 *
 * @package App\Http\Controllers\v1
 */
class MigrationController extends Controller
{
    /**
     * @ApiDocumentor (
     *     ignore: true
     * )
     *
     * @param MigrationRequest $request
     *
     * @return Response
     */
    public function migrate(MigrationRequest $request): Response
    {
        $databases = $request->get("databases");
        foreach ($databases as $group) {
            MigrationProcessor::migrate($group);
        }

        return response(null, Response::HTTP_NO_CONTENT);
    }
}
