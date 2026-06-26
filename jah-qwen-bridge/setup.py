"""
setup.py for Jah-Qwen Bridge
"""
from setuptools import setup, find_packages

setup(
    name="jah-bridge",
    version="1.0.0",
    packages=find_packages(),
    install_requires=[
        "requests>=2.28.0",
    ],
    python_requires=">=3.10",
    author="JAH",
    description="Python bridge connecting Qwen LLM to Jah-PHP tiered memory system",
)
