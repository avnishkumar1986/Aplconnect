param(
    [string]$ProjectPath = 'D:\xampp\htdocs\laravel_react'
)

$ErrorActionPreference = 'Stop'
$xamppPath = Split-Path -Parent (Split-Path -Parent $ProjectPath)
$phpCommand = Get-Command php.exe -ErrorAction SilentlyContinue
$phpPath = if ($null -ne $phpCommand) { $phpCommand.Source } else { Join-Path $xamppPath 'php\php.exe' }
$mysqlStartPath = Join-Path $xamppPath 'mysql_start.bat'
$npmPath = (Get-Command npm.cmd -ErrorAction Stop).Source
$logPath = Join-Path $ProjectPath 'storage\logs\local-dev'
$supervisorLog = Join-Path $logPath 'supervisor.log'
$mutex = [Threading.Mutex]::new($false, 'Local\LaravelReactLocalDevSupervisor')

if (-not $mutex.WaitOne(0, $false)) {
    exit 0
}

New-Item -ItemType Directory -Force -Path $logPath | Out-Null

function Write-SupervisorLog([string]$Message) {
    Add-Content -LiteralPath $supervisorLog -Value "$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss') $Message"
}

function Test-Port([int]$Port) {
    $client = [Net.Sockets.TcpClient]::new()
    try {
        $result = $client.BeginConnect('127.0.0.1', $Port, $null, $null)
        if (-not $result.AsyncWaitHandle.WaitOne(800)) { return $false }
        $client.EndConnect($result)
        return $true
    } catch {
        return $false
    } finally {
        $client.Dispose()
    }
}

function Start-LoggedProcess(
    [string]$Name,
    [string]$FilePath,
    [string[]]$ArgumentList,
    [string]$StdoutFile,
    [string]$StderrFile
) {
    Write-SupervisorLog "Starting $Name"
    return Start-Process -FilePath $FilePath -ArgumentList $ArgumentList `
        -WorkingDirectory $ProjectPath -WindowStyle Hidden `
        -RedirectStandardOutput $StdoutFile -RedirectStandardError $StderrFile -PassThru
}

try {
    Write-SupervisorLog 'Supervisor started.'
    $mysqlLastAttempt = [datetime]::MinValue
    $laravelLastAttempt = [datetime]::MinValue
    $viteLastAttempt = [datetime]::MinValue
    $queueProcess = $null

    while ($true) {
        $now = Get-Date

        if (-not (Test-Port 3306) -and ($now - $mysqlLastAttempt).TotalSeconds -ge 30) {
            $mysqlLastAttempt = $now
            Write-SupervisorLog 'MySQL is not listening on 3306; starting XAMPP MySQL.'
            Start-Process -FilePath $mysqlStartPath -WorkingDirectory $xamppPath -WindowStyle Hidden | Out-Null
        }

        if (-not (Test-Port 8001) -and ($now - $laravelLastAttempt).TotalSeconds -ge 10) {
            $laravelLastAttempt = $now
            Start-LoggedProcess 'Laravel' $phpPath `
                @('artisan', 'serve', '--host=127.0.0.1', '--port=8001', '--no-reload') `
                (Join-Path $logPath 'laravel.out.log') (Join-Path $logPath 'laravel.err.log') | Out-Null
        }

        if ($null -eq $queueProcess -or $queueProcess.HasExited) {
            $queueProcess = Start-LoggedProcess 'Laravel queue worker' $phpPath `
                @('-d', 'max_execution_time=0', '-d', 'memory_limit=1024M', 'artisan', 'queue:work', '--queue=api-imports,default', '--sleep=1', '--tries=1', '--timeout=900', '--memory=768') `
                (Join-Path $logPath 'queue.out.log') (Join-Path $logPath 'queue.err.log')
        }

        if (-not (Test-Port 5173) -and ($now - $viteLastAttempt).TotalSeconds -ge 10) {
            $viteLastAttempt = $now
            Start-LoggedProcess 'Vite' $npmPath `
                @('run', 'dev', '--', '--host', '127.0.0.1', '--port', '5173', '--strictPort') `
                (Join-Path $logPath 'vite.out.log') (Join-Path $logPath 'vite.err.log') | Out-Null
        }

        Start-Sleep -Seconds 5
    }
} catch {
    Write-SupervisorLog "Supervisor stopped: $($_.Exception.Message)"
    throw
} finally {
    $mutex.ReleaseMutex()
    $mutex.Dispose()
}
