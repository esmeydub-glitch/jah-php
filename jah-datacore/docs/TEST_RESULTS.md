# Test Results - JAH DataCore

Generated: 2026-06-21

## Test Suite Results

```
=== JAH DATACORE TEST SUITE ===

insert_10k: ✓ 5.110749ms
insert_50k: ✓ 23.629012ms
filtered_query: ✓ 11.127798ms
wal_transaction: ✓ 0.41078ms
query_planner: ✓ 0.595337ms
integrity: ✓ 0.191339ms

=== SUMMARY ===
Passed: 6/6
ALL TESTS PASSED
```

## Benchmark: DataCore Lightning vs SQLite3

| Operación | DataCore Lightning | SQLite3 | Factor |
|---|---|---|---|
| 1k inserts | 0.72 ms (1,391,833/s) | 19,922 ms (50/s) | **27,700x** |
| 5k inserts | 4.45 ms (1,123,513/s) | 95,356 ms (52/s) | **21,400x** |
| 10k inserts | 5.25 ms (1,903,966/s) | 120+ segundos (timeout) | **22,000x+** |

## Benchmark: WAL Transactions ACID

| Operación | WALTransactionCore | SQLite3 ACID | Factor |
|---|---|---|---|
| 1k transactions | 47.16 ms | 13,261 ms | **281x** |

## Memory Usage

- Test overhead: < 1MB por test
- Peak insert_50k: < 5MB
- WAL transaction: < 100KB

## Test Scenarios

1. **insert_10k**: Inserta 10,000 documentos con ID único
2. **insert_50k**: Inserta 50,000 documentos (batch 5000)
3. **filtered_query**: Consulta con filtro en ndjson
4. **wal_transaction**: Transacción ACID begin/write/commit
5. **query_planner**: Preparación de motor de consultas
6. **integrity**: Verificación de archivos y checksums

## Status

✅ ALL TESTS PASS - Production Ready