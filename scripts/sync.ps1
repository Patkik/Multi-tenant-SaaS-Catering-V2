param(
    [switch]$Release,
    [string]$Branch = 'main',
    [switch]$Force,
    [switch]$Help
)

if ($Help) {
    Write-Output "Usage: .\sync.ps1 [-Release] [-Branch <name>] [-Force]"
    Write-Output " -Release : checkout latest tag (default)"
    Write-Output " -Branch  : pull specified branch instead of release"
    Write-Output " -Force   : discard uncommitted changes"
    return
}

if (-not (git rev-parse --is-inside-work-tree 2>$null)) {
    Write-Error "Not a git repository. Run this inside the app folder."
    exit 2
}

git fetch --prune --tags origin

$status = git status --porcelain
if ($status) {
    if ($Force) {
        Write-Output "Discarding local changes (force)."
        git reset --hard
        git clean -fd
    } else {
        Write-Error "Uncommitted changes detected. Commit, stash, or rerun with -Force to discard."
        git status --porcelain
        exit 3
    }
}

if ($Release -or -not $Branch) {
    $tags = git tag --sort=-v:refname
    $latest = $tags | Select-Object -First 1
    if (-not $latest) {
        Write-Output "No tags found; falling back to branch $Branch"
        $mode = 'branch'
    } else {
        $current = git describe --tags --exact-match 2>$null
        if ($current -eq $latest) {
            Write-Output "Already on latest tag $latest"
        } else {
            Write-Output "Checking out latest tag: $latest"
            git checkout $latest
        }
    }
}

if (-not $Release -or $mode -eq 'branch') {
    Write-Output "Switching to branch $Branch and pulling latest from origin/$Branch"
    git fetch origin
    try {
        git checkout $Branch
    } catch {
        git checkout -b $Branch "origin/$Branch"
    }
    git pull --ff-only origin $Branch 2>$null || git pull origin $Branch
}

Write-Output "Sync complete. Current HEAD: $(git rev-parse --abbrev-ref HEAD 2>$null -replace '\n','')"
