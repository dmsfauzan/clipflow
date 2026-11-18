#!/usr/bin/env python3
"""Transkripsi audio lokal memakai faster-whisper.

Dipanggil oleh Laravel TranscriptionService sebagai proses independen, bukan lewat
HTTP request. Contoh:

    python scripts/transcribe.py --input audio.wav --output transcript.json \
        --model small --language id --device auto --compute-type int8

Skrip hanya menulis JSON ke file dan menampilkan pesan error di stderr.
"""
from __future__ import annotations

import argparse
import json
import os
import sys
from typing import Any

VALID_DEVICES = {"auto", "cpu", "cuda"}
VALID_COMPUTE_TYPES = {"default", "auto", "int8", "int8_float16", "float16", "float32"}


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Transkripsi audio dengan faster-whisper.")
    parser.add_argument("--input", required=True, help="Path file audio hasil ekstraksi FFmpeg.")
    parser.add_argument("--output", required=True, help="Path file JSON output.")
    parser.add_argument("--model", default="small", help="Nama model faster-whisper, mis. tiny/base/small/medium/large-v3.")
    parser.add_argument("--language", default="id", help="Kode bahasa ISO-639-1, mis. id untuk Bahasa Indonesia.")
    parser.add_argument("--device", default="auto", choices=sorted(VALID_DEVICES))
    parser.add_argument("--compute-type", default="int8", choices=sorted(VALID_COMPUTE_TYPES))
    parser.add_argument("--cpu-threads", type=int, default=4, help="Jumlah thread CPU faster-whisper.")
    return parser.parse_args()


def run(args: argparse.Namespace) -> int:
    input_path = os.path.abspath(args.input)
    output_path = os.path.abspath(args.output)

    if not os.path.isfile(input_path):
        print(f"Audio tidak ditemukan: {input_path}", file=sys.stderr)
        return 2

    try:
        from faster_whisper import WhisperModel
    except ImportError:
        print(
            "faster-whisper belum terpasang. Jalankan: pip install -r requirements.txt",
            file=sys.stderr,
        )
        return 3

    print(f"Memuat model faster-whisper '{args.model}' pada device={args.device}...", file=sys.stderr)

    try:
        model = WhisperModel(
            args.model,
            device=args.device,
            compute_type=args.compute_type,
            cpu_threads=max(1, args.cpu_threads),
        )
    except Exception as exception:  # noqa: BLE001
        print(f"Gagal memuat model Whisper: {exception}", file=sys.stderr)
        return 4

    try:
        segments_iter, info = model.transcribe(
            input_path,
            language=args.language,
            vad_filter=True,
            beam_size=5,
            vad_parameters=dict(min_silence_duration_ms=500),
        )
    except Exception as exception:  # noqa: BLE001
        print(f"Gagal transkripsi: {exception}", file=sys.stderr)
        return 5

    segments: list[dict[str, Any]] = []
    texts: list[str] = []

    for index, segment in enumerate(segments_iter, start=1):
        start = float(segment.start)
        end = float(segment.end)
        text = (segment.text or "").strip()

        if text and end > start:
            segments.append({"start": start, "end": end, "text": text})
            texts.append(text)

        if index % 10 == 0:
            print(f"Transkripsi: {index} segmen...", file=sys.stderr)

    if not segments:
        print("Transkripsi tidak menghasilkan segmen bertimestamp.", file=sys.stderr)
        return 6

    os.makedirs(os.path.dirname(output_path) or ".", exist_ok=True)

    payload = {
        "model": args.model,
        "language": getattr(info, "language", args.language),
        "text": " ".join(texts),
        "segments": segments,
    }

    with open(output_path, "w", encoding="utf-8") as handle:
        json.dump(payload, handle, ensure_ascii=False, indent=2)

    print(f"Transkripsi selesai: {len(segments)} segmen.", file=sys.stderr)
    return 0


def main() -> int:
    args = parse_args()

    try:
        return run(args)
    except KeyboardInterrupt:
        print("Transkripsi dibatalkan.", file=sys.stderr)
        return 130


if __name__ == "__main__":
    sys.exit(main())
