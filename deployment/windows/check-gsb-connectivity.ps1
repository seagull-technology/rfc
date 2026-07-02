$ErrorActionPreference = "Continue"

$targets = @(
    @{ Name = "GSB API host"; Host = "api-gateway.g2b.gsb.gov.jo"; Port = 9443 },
    @{ Name = "GSB STG IP"; Host = "10.0.26.123"; Port = 9443 },
    @{ Name = "GSB G2B IP"; Host = "10.0.3.170"; Port = 9443 },
    @{ Name = "GSB G2G IP"; Host = "10.0.28.180"; Port = 9443 }
)

foreach ($target in $targets) {
    Write-Host ""
    Write-Host ("Testing {0} ({1}:{2})" -f $target.Name, $target.Host, $target.Port)
    Test-NetConnection -ComputerName $target.Host -Port $target.Port
}
