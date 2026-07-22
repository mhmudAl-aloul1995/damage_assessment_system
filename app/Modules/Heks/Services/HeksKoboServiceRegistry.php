<?php

namespace App\Modules\Heks\Services;

class HeksKoboServiceRegistry
{
    /**
     * @return array<string, mixed>|null
     */
    public function service(string $serviceName): ?array
    {
        $canonical = $this->canonical($serviceName);

        if ($canonical === null) {
            return null;
        }

        $service = config("heks_kobo.services.{$canonical}");

        return is_array($service)
            ? array_merge($service, ['name' => $canonical])
            : null;
    }

    public function canonical(string $serviceName): ?string
    {
        $serviceName = trim($serviceName);
        $services = config('heks_kobo.services', []);

        if (isset($services[$serviceName])) {
            return $serviceName;
        }

        foreach ($services as $name => $service) {
            $aliases = $service['aliases'] ?? [];

            if (in_array($serviceName, $aliases, true)) {
                return (string) $name;
            }
        }

        return null;
    }

    public function storageName(string $serviceName): string
    {
        return $this->canonical($serviceName) ?? $serviceName;
    }

    public function accepts(string $serviceName): bool
    {
        return $this->canonical($serviceName) !== null;
    }

    /**
     * @return array<int, string>
     */
    public function lookupNames(string $serviceName): array
    {
        $canonical = $this->canonical($serviceName);
        $service = $canonical !== null ? $this->service($serviceName) : null;

        return array_values(array_unique(array_filter(array_merge(
            [$serviceName, str_replace('_', '-', $serviceName), str_replace('-', '_', $serviceName), $canonical],
            is_array($service) ? ($service['aliases'] ?? []) : [],
            is_array($service) ? ($service['mapping_services'] ?? []) : [],
        ))));
    }

    public function wideTable(string $serviceName): ?string
    {
        $service = $this->service($serviceName);

        return is_string($service['wide_table'] ?? null) ? $service['wide_table'] : null;
    }
}
