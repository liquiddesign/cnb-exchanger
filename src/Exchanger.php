<?php

declare(strict_types=1);

namespace Exchanger;

use Nette\Caching\Cache;
use Nette\Caching\Storage;
use Nette\Utils\DateTime;

class Exchanger
{
	private const SOURCE_URL = 'https://www.cnb.cz/cs/financni_trhy/devizovy_trh/kurzy_devizoveho_trhu/denni_kurz.txt?date=%s';
	
	private Cache $cache;
	
	private string $cacheExpiration;
	
	private DateTime $exchangeDate;
	
	private string $rootCurrencyCode;
	
	public function __construct(Storage $storage, string $rootCurrencyCode = 'CZK', string $cacheExpiration = '1 hour')
	{
		$this->cache = new Cache($storage);
		$this->cacheExpiration = $cacheExpiration;
		$this->exchangeDate = new DateTime();
		$this->rootCurrencyCode = $rootCurrencyCode;
	}
	
	public function getExchangeDate(): ?DateTime
	{
		return $this->exchangeDate;
	}
	
	public function setExchangeDate(?DateTime $exchangeDate): void
	{
		$this->exchangeDate = $exchangeDate ?? new DateTime();
	}
	
	public function exchange(float $value, string $targetCurrency, string $fromCurrency = 'CZK'): float
	{
		if ($targetCurrency === $fromCurrency) {
			return $value;
		}
		
		$fromRate = $fromCurrency === $this->rootCurrencyCode ? 1 : $this->getRate($fromCurrency);
		$targetRate = $targetCurrency === $this->rootCurrencyCode ? 1 : $this->getRate($targetCurrency);
		
		$czk = $value * $fromRate;
		
		return $czk / $targetRate;
	}
	
	/**
	 * @throws \Throwable
	 */
	public function getRateListContent(?DateTime $datetime = null): string
	{
		$datetime = $datetime ?: $this->exchangeDate;
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
	protected function getRate(string $targetCurrency): float
	{
		$match = null;
		\preg_match('/' . $targetCurrency . '\|([0-9,]+)$/m', $this->getRateListContent($this->exchangeDate), $match);
		
		$match = \preg_replace('/,/', '.', $match[1] ?? '');
		
		if (!$match) {
			throw new \Exception("Cannot find $targetCurrency list");
		}
		
		return \floatval($match);
	}
}
