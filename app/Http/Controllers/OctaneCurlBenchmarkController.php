<?php
declare (strict_types = 1);
namespace App\Http\Controllers;

set_time_limit(0); // Definir o tempo limite para zero para removê-lo

use Illuminate\Http\Client\Pool;
use Illuminate\Http\Request;
use Illuminate\Support\Benchmark;
use Illuminate\Support\Facades\Http;
use Laravel\Octane\Facades\Octane;

class OctaneCurlBenchmarkController extends Controller
{
    private int $qtd_endpoints = 100;
    public array $endpoints = [];
    public array $httpLaravelResult = [];
    public array $httpCurlResult = [];
    public array $multiHttpPoolLaravelResult = [];
    public array $multiGetCurlResult = [];
    public array $httpOctaneResult = [];
    public array $httpChunkedOctaneResult = [];
    public array $multiHttpPoolOctaneResult = [];

    /**
     * $request['mode'] => default, httpLaravel, httpCurl, multiHttpPoolLaravel, multiGetCurl, httpOctane, httpChunkedOctane, multiHttpPoolOctane
     * $request['endpoints'] => 10
     * $request['try'] => 1
     * $request['disable-octane'] => null
     */
    public function run(Request $request)
    {

        $this->clearEndpoints();
        $this->qtd_endpoints = (int) $request->input('endpoints', $this->qtd_endpoints);
        $this->generateEndpoints();

        $bench = match ($request->input('mode', null)) {
            '1', 'httpLaravel' => ['httpLaravel' => fn() => $this->httpLaravel()],
            '2', 'httpCurl' => ['httpCurl' => fn() => $this->httpCurl()],
            '3', 'multiHttpPoolLaravel' => ['multiHttpPoolLaravel' => fn() => $this->multiHttpPoolLaravel()],
            '4', 'multiGetCurl' => ['multiGetCurl' => fn() => $this->multiGetCurl()],
            '5', 'httpOctane' => ['httpOctane' => fn() => $this->httpOctane()],
            '6', 'httpChunkedOctane' => ['httpChunkedOctane' => fn() => $this->httpChunkedOctane()],
            '7', 'multiHttpPoolOctane' => ['multiHttpPoolOctane' => fn() => $this->multiHttpPoolOctane()],
            default => [
                'httpLaravel' => fn() => $this->httpLaravel(),
                'httpCurl' => fn() => $this->httpCurl(),
                'multiHttpPoolLaravel' => fn() => $this->multiHttpPoolLaravel(),
                'multiGetCurl' => fn() => $this->multiGetCurl(),
                'httpOctane' => fn() => $this->httpOctane(),
                'httpChunkedOctane' => fn() => $this->httpChunkedOctane(),
                'multiHttpPoolOctane' => fn() => $this->multiHttpPoolOctane(),
            ]
        };

        if ($request->input('disable-octane', null)) {
            unset($bench['httpOctane'], $bench['multiHttpPoolOctane'], $bench['httpChunkedOctane']);
        }

        $try = (int) $request->input('try', 1);
        $result = Benchmark::measure($bench, $try);

        logs()->info($result);

        $results_status = [
            "httpLaravel" => $this->httpLaravelResult,
            "httpCurl" => $this->httpCurlResult,
            "multiHttpPoolLaravel" => $this->multiHttpPoolLaravelResult,
            "multiGetCurl" => $this->multiGetCurlResult,
            "httpOctane" => $this->httpOctaneResult,
            "httpChunkedOctane" => $this->httpChunkedOctaneResult,
            "multiHttpPoolOctane" => $this->multiHttpPoolOctaneResult,
        ];

        $results_success_count = [];
        foreach ($results_status as $key => $values) {
            $results_success_count[$key] = 0;
            foreach ($values as $value) {
                if ($value) {
                    $results_success_count[$key]++;
                }
            }
        }

        return response()->json([
            "httpLaravel" => number_format((!empty($result['httpLaravel']) ? $result['httpLaravel'] / 1000 : 0), 2, ',', '.'),
            "httpLaravel-media" => number_format((!empty($result['httpLaravel']) ? ($result['httpLaravel'] / 1000) / $this->qtd_endpoints : 0), 4, ',', '.'),
            "httpCurl" => number_format((!empty($result['httpCurl']) ? $result['httpCurl'] / 1000 : 0), 2, ',', '.'),
            "httpCurl-media" => number_format((!empty($result['httpCurl']) ? ($result['httpCurl'] / 1000) / $this->qtd_endpoints : 0), 4, ',', '.'),
            "multiHttpPoolLaravel" => number_format((!empty($result['multiHttpPoolLaravel']) ? $result['multiHttpPoolLaravel'] / 1000 : 0), 2, ',', '.'),
            "multiHttpPoolLaravel-media" => number_format((!empty($result['multiHttpPoolLaravel']) ? ($result['multiHttpPoolLaravel'] / 1000) / $this->qtd_endpoints : 0), 4, ',', '.'),
            "multiGetCurl" => number_format((!empty($result['multiGetCurl']) ? $result['multiGetCurl'] / 1000 : 0), 2, ',', '.'),
            "multiGetCurl-media" => number_format((!empty($result['multiGetCurl']) ? ($result['multiGetCurl'] / 1000) / $this->qtd_endpoints : 0), 4, ',', '.'),
            "httpOctane" => number_format((!empty($result['httpOctane']) ? $result['httpOctane'] / 1000 : 0), 2, ',', '.'),
            "httpOctane-media" => number_format((!empty($result['httpOctane']) ? ($result['httpOctane'] / 1000) / $this->qtd_endpoints : 0), 4, ',', '.'),
            "httpChunkedOctane" => number_format((!empty($result['httpChunkedOctane']) ? $result['httpChunkedOctane'] / 1000 : 0), 2, ',', '.'),
            "httpChunkedOctane-media" => number_format((!empty($result['httpChunkedOctane']) ? ($result['httpChunkedOctane'] / 1000) / $this->qtd_endpoints : 0), 4, ',', '.'),
            "multiHttpPoolOctane" => number_format((!empty($result['multiHttpPoolOctane']) ? $result['multiHttpPoolOctane'] / 1000 : 0), 2, ',', '.'),
            "multiHttpPoolOctane-media" => number_format((!empty($result['multiHttpPoolOctane']) ? ($result['multiHttpPoolOctane'] / 1000) / $this->qtd_endpoints : 0), 4, ',', '.'),
            "results_success_count" => $results_success_count,
            "info" => [
                "mode" => $request->input('mode', null),
                "endpoints" => $this->qtd_endpoints,
                "try" => $try,
            ],
        ]);
    }

    private function clearEndpoints(): void
    {
        $this->endpoints = [];
    }

    private function generateEndpoints(): void
    {
        $start = 2087113099;
        foreach (range(0, $this->qtd_endpoints) as $key) {
            $this->endpoints[] = "https://api.mercadolibre.com/items/MLB" . ($start + $key);
        }
    }

    public function httpLaravel(): array
    {
        $results = [];
        foreach ($this->endpoints as $endpoint) {
            $results[$endpoint] = Http::get($endpoint)->successful();
        }
        $this->httpLaravelResult = $results;
        return $results;
    }

    public function httpCurl(): array
    {
        $results = [];
        foreach ($this->endpoints as $endpoint) {
            $ch = curl_init($endpoint);

            // Defina as opções da requisição
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Habilita o retorno da resposta
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Seguir redirecionamentos (se houver)

            // Execute a requisição
            curl_exec($ch);

            // Verifique se ocorreram erros
            if (curl_errno($ch)) {
                $results[$endpoint] = false;
            } else {
                curl_close($ch);
                if (curl_getinfo($ch)['http_code'] === 200) {
                    $results[$endpoint] = true;
                }
            }
        }

        $this->httpCurlResult = $results;
        return $results;
    }

    public function multiHttpPoolLaravel(): array
    {
        $results = [];
        $_results = Http::pool(function (Pool $pool) {
            foreach ($this->endpoints as $endpoint) {
                $pool->as($endpoint)->get($endpoint);
            }
        });

        foreach ($_results as $key => $_result) {
            if (!empty($_result)) {
                $results[$key] = $_result->successful();
            }
        }

        // $results = Http::pool(fn(Pool $pool) => [
        //     $pool->as("https://api.mercadolibre.com/items/MLB2087113099")->get("https://api.mercadolibre.com/items/MLB2087113099"),
        //     $pool->as("https://api.mercadolibre.com/items/MLB2087113100")->get("https://api.mercadolibre.com/items/MLB2087113100"),
        //     $pool->as("https://api.mercadolibre.com/items/MLB2087113101")->get("https://api.mercadolibre.com/items/MLB2087113101"),
        //     $pool->as("https://api.mercadolibre.com/items/MLB2087113102")->get("https://api.mercadolibre.com/items/MLB2087113102"),
        //     $pool->as("https://api.mercadolibre.com/items/MLB2087113103")->get("https://api.mercadolibre.com/items/MLB2087113103"),
        //     $pool->as("https://api.mercadolibre.com/items/MLB2087113104")->get("https://api.mercadolibre.com/items/MLB2087113104"),
        //     $pool->as("https://api.mercadolibre.com/items/MLB2087113105")->get("https://api.mercadolibre.com/items/MLB2087113105"),
        //     $pool->as("https://api.mercadolibre.com/items/MLB2087113106")->get("https://api.mercadolibre.com/items/MLB2087113106"),
        //     $pool->as("https://api.mercadolibre.com/items/MLB2087113107")->get("https://api.mercadolibre.com/items/MLB2087113107"),
        //     $pool->as("https://api.mercadolibre.com/items/MLB2087113108")->get("https://api.mercadolibre.com/items/MLB2087113108"),
        //     $pool->as("https://api.mercadolibre.com/items/MLB2087113109")->get("https://api.mercadolibre.com/items/MLB2087113109"),
        // ]);
        // $results = [
        //     "https://api.mercadolibre.com/items/MLB2087113099" => $results["https://api.mercadolibre.com/items/MLB2087113099"]->successful(),
        //     "https://api.mercadolibre.com/items/MLB2087113100" => $results["https://api.mercadolibre.com/items/MLB2087113100"]->successful(),
        //     "https://api.mercadolibre.com/items/MLB2087113101" => $results["https://api.mercadolibre.com/items/MLB2087113101"]->successful(),
        //     "https://api.mercadolibre.com/items/MLB2087113102" => $results["https://api.mercadolibre.com/items/MLB2087113102"]->successful(),
        //     "https://api.mercadolibre.com/items/MLB2087113103" => $results["https://api.mercadolibre.com/items/MLB2087113103"]->successful(),
        //     "https://api.mercadolibre.com/items/MLB2087113104" => $results["https://api.mercadolibre.com/items/MLB2087113104"]->successful(),
        //     "https://api.mercadolibre.com/items/MLB2087113105" => $results["https://api.mercadolibre.com/items/MLB2087113105"]->successful(),
        //     "https://api.mercadolibre.com/items/MLB2087113106" => $results["https://api.mercadolibre.com/items/MLB2087113106"]->successful(),
        //     "https://api.mercadolibre.com/items/MLB2087113107" => $results["https://api.mercadolibre.com/items/MLB2087113107"]->successful(),
        //     "https://api.mercadolibre.com/items/MLB2087113108" => $results["https://api.mercadolibre.com/items/MLB2087113108"]->successful(),
        //     "https://api.mercadolibre.com/items/MLB2087113109" => $results["https://api.mercadolibre.com/items/MLB2087113109"]->successful(),
        // ];
        $this->multiHttpPoolLaravelResult = $results;
        return $results;
    }

    public function multiGetCurl()
    {

        $multiCurls = [];
        $results = [];
        $curl_multi = curl_multi_init();
        foreach ($this->endpoints as $endpoint) {
            $multiCurls[$endpoint] = curl_init();
            curl_setopt($multiCurls[$endpoint], CURLOPT_URL, $endpoint);
            curl_setopt($multiCurls[$endpoint], CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($multiCurls[$endpoint], CURLOPT_RETURNTRANSFER, true);
            curl_multi_add_handle($curl_multi, $multiCurls[$endpoint]);
        }
        $index = null;
        do {
            curl_multi_exec($curl_multi, $index);
        } while ($index > 0);

        foreach ($multiCurls as $key => $result) {
            // if (curl_getinfo($multiCurls[$key])['http_code'] === 200) {
            //     $results[$key] = true;
            // }
            $_result = curl_multi_getcontent($result);
            $results[$key] = !str_contains($_result, '"status":404');
            curl_multi_remove_handle($curl_multi, $multiCurls[$key]);
        }
        curl_multi_close($curl_multi);
        $this->multiGetCurlResult = $results;
        return $results;
    }

    public function httpOctane()
    {

        $reqs = [];
        $results = [];
        foreach ($this->endpoints as $endpoint) {
            $reqs[$endpoint] = fn() => Http::get($endpoint);
        }

        // $chunks = array_chunk($reqs, 10);
        // foreach ($chunks as $chunk) {
        // $_results = Octane::concurrently($chunk);
        $_results = Octane::concurrently($reqs, 10000);
        foreach ($_results as $key => $_result) {
            if (!empty($_result)) {
                $results[$key] = $_result->successful();
            }
        }
        // }

        // $_results = Octane::concurrently([
        //     "https://api.mercadolibre.com/items/MLB2087113099" => fn() => Http::get("https://api.mercadolibre.com/items/MLB2087113099"),
        //     "https://api.mercadolibre.com/items/MLB2087113100" => fn() => Http::get("https://api.mercadolibre.com/items/MLB2087113100"),
        //     "https://api.mercadolibre.com/items/MLB2087113101" => fn() => Http::get("https://api.mercadolibre.com/items/MLB2087113101"),
        //     "https://api.mercadolibre.com/items/MLB2087113102" => fn() => Http::get("https://api.mercadolibre.com/items/MLB2087113102"),
        //     "https://api.mercadolibre.com/items/MLB2087113103" => fn() => Http::get("https://api.mercadolibre.com/items/MLB2087113103"),
        //     "https://api.mercadolibre.com/items/MLB2087113104" => fn() => Http::get("https://api.mercadolibre.com/items/MLB2087113104"),
        //     "https://api.mercadolibre.com/items/MLB2087113105" => fn() => Http::get("https://api.mercadolibre.com/items/MLB2087113105"),
        //     "https://api.mercadolibre.com/items/MLB2087113106" => fn() => Http::get("https://api.mercadolibre.com/items/MLB2087113106"),
        //     "https://api.mercadolibre.com/items/MLB2087113107" => fn() => Http::get("https://api.mercadolibre.com/items/MLB2087113107"),
        //     "https://api.mercadolibre.com/items/MLB2087113108" => fn() => Http::get("https://api.mercadolibre.com/items/MLB2087113108"),
        //     "https://api.mercadolibre.com/items/MLB2087113109" => fn() => Http::get("https://api.mercadolibre.com/items/MLB2087113109"),
        // ]);

        $this->httpOctaneResult = $results;
        return $results;
    }

    public function httpChunkedOctane()
    {

        $reqs = [];
        $results = [];
        foreach ($this->endpoints as $endpoint) {
            $reqs[$endpoint] = fn() => Http::get($endpoint);
        }

        $chunks = array_chunk($reqs, 10, true);
        foreach ($chunks as $chunk) {
            $concurrentlys = Octane::concurrently($chunk, 10000);
            foreach ($concurrentlys as $key => $_result) {
                if (!empty($_result)) {
                    $results[$key] = $_result->successful();
                }
            }
        }
        $this->httpChunkedOctaneResult = $results;
        return $results;
    }

    public function multiHttpPoolOctane()
    {
        $results = [];
        $chunks = array_chunk($this->endpoints, 10, true);

        $reqs = [];
        foreach ($chunks as $chunk) {
            $reqs[] = fn() => Http::pool(function (Pool $pool) use ($chunk) {
                foreach ($chunk as $endpoint) {
                    $pool->as($endpoint)->get($endpoint);
                }
            });
        }

        $_results_all = Octane::concurrently($reqs, 10000);
        foreach ($_results_all as $key_all => $_results) {
            foreach ($_results as $key => $_result) {
                if (!empty($_result)) {
                    $results[$key] = $_result->successful();
                }
            }
        }

        $this->multiHttpPoolOctaneResult = $results;
        return $results;
    }

}
