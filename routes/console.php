<?php

use App\Jobs\AgregarFaturamentoJob;
use App\Jobs\BuscarEdiPagBankJob;
use App\Jobs\CalcularRoyaltiesJob;
use App\Jobs\RenovarTokenPagBankJob;
use App\Jobs\SincronizarEmailsJob;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new RenovarTokenPagBankJob)->dailyAt('04:00');
Schedule::job(new BuscarEdiPagBankJob)->dailyAt('06:00');
Schedule::job(new CalcularRoyaltiesJob)->everyFifteenMinutes();
Schedule::job(new AgregarFaturamentoJob)->dailyAt('02:00');
Schedule::job(new SincronizarEmailsJob)->everyFiveMinutes();
