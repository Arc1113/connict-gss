import argparse
import importlib.metadata
import json
import re
import shutil
import sys
from pathlib import Path


LICENSE_FILE_PATTERN = re.compile(
    r"^(license|licence|copying|notice|authors)(\..*)?$",
    re.IGNORECASE,
)


def safe_name(value: str) -> str:
    return re.sub(r"[^0-9A-Za-z._-]+", "-", value).strip("-") or "unknown"


def copy_license_file(source: Path, destination: Path) -> str:
    destination.parent.mkdir(parents=True, exist_ok=True)
    shutil.copy2(source, destination)
    return destination.as_posix()


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--output-directory", required=True)
    parser.add_argument("--notice-path", required=True)
    parser.add_argument("--model-directory", required=True)
    parser.add_argument("--model-revision", required=True)
    args = parser.parse_args()

    output_directory = Path(args.output_directory).resolve()
    notice_path = Path(args.notice_path).resolve()
    model_directory = Path(args.model_directory).resolve()
    output_directory.mkdir(parents=True, exist_ok=True)

    packages = []
    for distribution in sorted(
        importlib.metadata.distributions(),
        key=lambda item: (item.metadata.get("Name") or "").casefold(),
    ):
        name = distribution.metadata.get("Name") or "unknown"
        package_directory = output_directory / safe_name(name)
        license_files = []

        for relative in distribution.files or []:
            if not LICENSE_FILE_PATTERN.match(Path(str(relative)).name):
                continue
            source = Path(distribution.locate_file(relative))
            if not source.is_file():
                continue
            destination = package_directory / safe_name(Path(str(relative)).name)
            copy_license_file(source, destination)
            license_files.append(destination.relative_to(output_directory).as_posix())

        packages.append(
            {
                "name": name,
                "version": distribution.version,
                "license": distribution.metadata.get("License-Expression")
                or distribution.metadata.get("License")
                or "See package metadata and upstream project",
                "project_url": distribution.metadata.get("Home-page")
                or next(
                    (
                        value.split(",", 1)[-1].strip()
                        for value in distribution.metadata.get_all("Project-URL", [])
                        if "," in value
                    ),
                    "",
                ),
                "license_files": sorted(set(license_files)),
            }
        )

    python_license = Path(sys.base_prefix) / "LICENSE.txt"
    python_license_relative = ""
    if python_license.is_file():
        destination = output_directory / "Python" / "LICENSE.txt"
        copy_license_file(python_license, destination)
        python_license_relative = destination.relative_to(output_directory).as_posix()

    model_license_files = []
    for source in sorted(model_directory.rglob("*")):
        if not source.is_file() or not LICENSE_FILE_PATTERN.match(source.name):
            continue
        destination = output_directory / "Kokoro-82M" / safe_name(source.name)
        copy_license_file(source, destination)
        model_license_files.append(destination.relative_to(output_directory).as_posix())

    inventory = {
        "schema_version": 1,
        "python": sys.version,
        "python_license_file": python_license_relative,
        "model": {
            "name": "hexgrad/Kokoro-82M",
            "revision": args.model_revision,
            "source_url": f"https://huggingface.co/hexgrad/Kokoro-82M/tree/{args.model_revision}",
            "license_files": sorted(set(model_license_files)),
        },
        "packages": packages,
    }
    (output_directory / "license-inventory.json").write_text(
        json.dumps(inventory, indent=2, ensure_ascii=False),
        encoding="utf-8",
    )

    lines = [
        "Connict GSS Offline TTS - Third-Party Notices",
        "",
        "This inventory is generated from the exact Python environment and model",
        "used for this Windows bundle. Corresponding license files are stored in",
        "the licenses directory. Legal approval remains a release-owner decision.",
        "",
        f"Python: {sys.version.split()[0]}",
        f"Kokoro model revision: {args.model_revision}",
        "",
        "Python packages:",
    ]
    for package in packages:
        files = ", ".join(package["license_files"]) or "no packaged license file; inspect upstream metadata"
        lines.extend(
            [
                "",
                f"- {package['name']} {package['version']}",
                f"  License: {package['license']}",
                f"  Project: {package['project_url'] or 'not declared'}",
                f"  Files: {files}",
            ]
        )

    notice_path.parent.mkdir(parents=True, exist_ok=True)
    notice_path.write_text("\n".join(lines) + "\n", encoding="utf-8")


if __name__ == "__main__":
    main()
