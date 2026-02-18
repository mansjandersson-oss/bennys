#!/usr/bin/env python3
"""Startar Benny's Motorworks lokalt som en enkel app.

- Startar PHP:s inbyggda server
- Öppnar webbläsaren automatiskt
- Stoppar servern med Ctrl+C
"""

import os
import shutil
import subprocess
import sys
import time
import webbrowser
from pathlib import Path

HOST = "127.0.0.1"
PORT = 8000


def main() -> int:
    project_dir = Path(__file__).resolve().parent
    php_path = os.environ.get("BENNYS_PHP_EXE") or shutil.which("php")

    if php_path is None:
        print("[Fel] PHP hittades inte i PATH.")
        print("Installera PHP och försök igen.")
        return 1

    print("Startar Benny's Motorworks lokalt...")
    print(f"Projektmapp: {project_dir}")
    print(f"PHP: {php_path}")
    print(f"URL: http://{HOST}:{PORT}")

    process = subprocess.Popen(
        [php_path, "-S", f"{HOST}:{PORT}"],
        cwd=str(project_dir),
    )

    try:
        time.sleep(1)
        webbrowser.open(f"http://{HOST}:{PORT}")
        print("\nAppen körs. Tryck Ctrl+C för att stänga.")
        process.wait()
    except KeyboardInterrupt:
        print("\nStänger appen...")
    finally:
        if process.poll() is None:
            process.terminate()
            try:
                process.wait(timeout=5)
            except subprocess.TimeoutExpired:
                process.kill()

    return 0


if __name__ == "__main__":
    sys.exit(main())
