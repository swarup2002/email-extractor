<?php

if (!function_exists('retry')) {
    /**
     * Retry an operation a given number of times.
     *
     * @param  int  $times
     * @param  callable  $callback
     * @param  int  $sleep
     * @param  callable|null  $when
     * @return mixed
     *
     * @throws \Exception
     */
    function retry($times, callable $callback, $sleep = 0, $when = null)
    {
        $attempts = 0;
        
        // Store the last exception to throw if we run out of attempts
        $lastException = null;
        
        while ($attempts < $times) {
            $attempts++;
            
            try {
                return $callback($attempts);
            } catch (\Exception $e) {
                $lastException = $e;
                
                if ($when && !$when($e)) {
                    throw $e;
                }
                
                if ($attempts >= $times) {
                    break;
                }
                
                if ($sleep) {
                    usleep($sleep * 1000);
                }
            }
        }
        
        throw $lastException ?? new \Exception("Maximum retry attempts reached without success");
    }
} 