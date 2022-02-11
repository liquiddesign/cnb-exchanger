<?php

declare(strict_types=1);

namespace Exchanger;

use Carbon\Carbon;
use Nette\Caching\Cache;
use Nette\Caching\Storage;

class Exchanger
{
	private const SOURCE_URL = 'https://www.cnb.cz/cs/financni_trhy/devizovy_trh/kurzy_devizoveho_trhu/denni_kurz.txt?date=%s';
	
	private Cache $cache;
	
	private string $cacheExpiration;
	
	private string $rootCurrencyCode;
	
	public function __construct(Storage $storage, string $rootCurrencyCode = 'CZK', string $cacheExpiration = '1 hour')
	{
		$this->cache = new Cache($storage);
		$this->cacheExpiration = $cacheExpiration;
		$this->rootCurrencyCode = $rootCurrencyCode;
	}
	
	public function exchange(float $value, string $targetCurrency, string $fromCurrency = 'CZK', ?Carbon $exchangeDate = null): float
	{
		if ($targetCurrency === $fromCurrency) {
			return $value;
		}
		
		$exchangeDate = $exchangeDate ?: Carbon::now();
		
		$fromRate = $fromCurrency === $this->rootCurrencyCode ? 1 : $this->getRate($fromCurrency, $exchangeDate);
		$targetRate = $targetCurrency === $this->rootCurrencyCode ? 1 : $this->getRate($targetCurrency, $exchangeDate);
		
		$czk = $value * $fromRate;
		
		return $czk / $targetRate;
	}
	
	/**
	 * @throws \Throwable
	 */
	public function getRateListContent(?Carbon $datetime = null): string
	{
		$datetime = $datetime ?: Carbon::now();
		$date = $datetime->format('Y-m-d');
		$cacheExpiration = $this->cacheExpiration;
		
		return $this->cache->load('currency_rate_list_' . $date, function (&$dependencies) use ($date, $cacheExpiration) {
			$dependencies[Cache::EXPIRE] = $cacheExpiration;
			
			$content = \file_get_contents(\sprintf(self::SOURCE_URL, $date));
			
			if (!$content) {
				throw new \Exception('Cannot find currency list');
			}
			
			return $content;
		});
	}
	
	/**
	 * @throws \Throwable
	 */
	protected function getRate(string $targetCurrency, Carbon $exchangeDate): float
	{
		$match = null;
		\preg_match('/' . $targetCurrency . '\|([0-9,]+)$/m', $this->getRateListContent($exchangeDate), $match);
		
		$match = \preg_replace('/,/', '.', $match[1] ?? '');
		
		if (!$match) {
			throw new \Exception("Cannot find $targetCurrency list");
		}
		
		return \floatval($match);
	}
}
