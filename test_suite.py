#!/usr/bin/env python3
"""
test_suite.py — Pruebas automatizadas para JAH-Qwen Bridge
Sin dependencias externas. Ejecutar: python3 test_suite.py
"""

import urllib.request
import urllib.parse
import json
import os
import sys
import time

BASE_URL = "http://localhost:8000"
PASSED = 0
FAILED = 0

def test(name, condition, detail=""):
    global PASSED, FAILED
    if condition:
        PASSED += 1
        print(f"  ✅ {name}")
    else:
        FAILED += 1
        print(f"  ❌ {name} — {detail}")

def api_get(action, params=None):
    url = f"{BASE_URL}/api.php?action={action}"
    if params:
        url += "&" + urllib.parse.urlencode(params)
    req = urllib.request.Request(url)
    with urllib.request.urlopen(req, timeout=10) as resp:
        return json.loads(resp.read().decode())

def api_post(action, data):
    body = json.dumps({"action": action, **data}).encode()
    req = urllib.request.Request(f"{BASE_URL}/api.php", data=body, headers={"Content-Type": "application/json"})
    try:
        with urllib.request.urlopen(req, timeout=10) as resp:
            return json.loads(resp.read().decode())
    except urllib.error.HTTPError as e:
        return json.loads(e.read().decode())

def agent_post(message, collection="memories"):
    body = json.dumps({"message": message, "collection": collection}).encode()
    req = urllib.request.Request(f"{BASE_URL}/agent.php", data=body, headers={"Content-Type": "application/json"})
    try:
        with urllib.request.urlopen(req, timeout=30) as resp:
            return json.loads(resp.read().decode())
    except urllib.error.HTTPError as e:
        return json.loads(e.read().decode())

# ============================================================
print("\n" + "="*60)
print("SUITE DE PRUEBAS — JAH-Qwen Bridge")
print("="*60)

# ------------------------------------------------------------
print("\n[1] CONECTIVIDAD")
# ------------------------------------------------------------
try:
    r = urllib.request.urlopen(f"{BASE_URL}/api.php", timeout=5)
    data = json.loads(r.read().decode())
    test("Servidor responde HTTP 200", r.status == 200, f"status={r.status}")
    test("Servicio identificado", data.get("service") == "Jah-Qwen Bridge API", f"service={data.get('service')}")
    test("Motor DataCore activo", "DataCoreTurbo" in data.get("storage_engine", ""), f"engine={data.get('storage_engine')}")
except Exception as e:
    test("Servidor responde", False, str(e))
    print("\n❌ CRÍTICO: Servidor no responde. Abortando.")
    sys.exit(1)

# ------------------------------------------------------------
print("\n[2] ALMACENAMIENTO (DataCore Binario)")
# ------------------------------------------------------------
save_result = api_post("save", {
    "collection": "test",
    "tier": "hot",
    "data": {"id": "test_001", "content": "El usuario prefiere PHP y tema Dracula", "tags": ["lang:php", "theme:dracula"]}
})
test("Save retorna success", save_result.get("status") == "success", json.dumps(save_result))
test("ID correcto", save_result.get("id") == "test_001", f"id={save_result.get('id')}")
test("Colección correcta", save_result.get("collection") == "test", f"collection={save_result.get('collection')}")

bin_dir = "/home/salkesme/Documentos/jah php/jah php/memory/datacore/data/"
idx_dir = "/home/salkesme/Documentos/jah php/jah php/memory/datacore/index/"
bin_files = os.listdir(bin_dir) if os.path.isdir(bin_dir) else []
idx_files = os.listdir(idx_dir) if os.path.isdir(idx_dir) else []
test("Archivo .bin creado", any(f.endswith(".bin") for f in bin_files), f"files: {bin_files}")
test("Archivo .idx creado", any(f.endswith(".idx") for f in idx_files), f"files: {idx_files}")

# ------------------------------------------------------------
print("\n[3] BÚSQUEDA FULL-TEXT")
# ------------------------------------------------------------
search_result = api_post("search", {"collection": "test", "query": "PHP Dracula"})
test("Search retorna resultados", search_result.get("count", 0) > 0, json.dumps(search_result))
test("Encuentra el documento", any(d.get("id") == "test_001" for d in search_result.get("data", [])))

search_empty = api_post("search", {"collection": "test", "query": "xyz_no_existe_12345"})
test("Search sin resultados retorna 0", search_empty.get("count", 0) == 0, f"count={search_empty.get('count')}")

# ------------------------------------------------------------
print("\n[4] RECUPERACIÓN POR ID")
# ------------------------------------------------------------
retrieve = api_post("retrieve", {"collection": "test", "id": "test_001"})
test("Retrieve exitoso", retrieve.get("status") == "success", json.dumps(retrieve))
test("Contenido correcto", "PHP" in retrieve.get("data", {}).get("content", ""), json.dumps(retrieve.get("data", {})))

retrieve_missing = api_post("retrieve", {"collection": "test", "id": "no_existe_999"})
test("ID inexistente retorna not_found", retrieve_missing.get("status") == "not_found", json.dumps(retrieve_missing))

# ------------------------------------------------------------
print("\n[5] BATCH INSERT (100 docs)")
# ------------------------------------------------------------
docs = [{"id": f"batch_{i}", "content": f"Documento de prueba #{i}"} for i in range(100)]
batch_result = api_post("batch", {"collection": "test", "docs": docs})
test("Batch insert 100 docs", batch_result.get("inserted", 0) == 100, f"inserted={batch_result.get('inserted')}")

# ------------------------------------------------------------
print("\n[6] ESTADÍSTICAS")
# ------------------------------------------------------------
stats = api_get("stats")
test("Stats retorna datos", "turbo" in stats.get("data", {}), json.dumps(stats))
test("Documentos en DataCore", stats.get("data", {}).get("turbo", {}).get("documents", 0) > 0, json.dumps(stats.get("data", {}).get("turbo", {})))

# ------------------------------------------------------------
print("\n[7] AGENTE QWEN — CONTEXTO RECUPERADO")
# ------------------------------------------------------------
api_post("save", {
    "collection": "test",
    "tier": "hot",
    "data": {"id": "color_pref", "content": "Al usuario le gusta el color oscuro, tema Dracula para programar", "tags": ["preference"]}
})

agent_result = agent_post("¿De qué color me gusta programar?", "test")
test("Agente retorna success", agent_result.get("status") == "success", json.dumps(agent_result))
test("Contexto fue usado", agent_result.get("context_used", 0) > 0, f"context_used={agent_result.get('context_used')}")
response_text = agent_result.get("response", "")
test("Respuesta menciona color/Dracula", "oscuro" in response_text.lower() or "dracula" in response_text.lower(), f"response={response_text[:80]}")

# ------------------------------------------------------------
print("\n[8] AGENTE QWEN — MEMORIA PERSISTENTE")
# ------------------------------------------------------------
time.sleep(1)
search_after = api_post("search", {"collection": "test", "query": "color_preferido"})
test("Interacción guardada en memoria", search_after.get("count", 0) > 0, f"count={search_after.get('count')}")

# ------------------------------------------------------------
print("\n[9] ELIMINACIÓN (SOFT-DELETE)")
# ------------------------------------------------------------
delete_result = api_post("delete", {"collection": "test", "id": "test_001"})
test("Delete retorna success", delete_result.get("status") == "success", json.dumps(delete_result))

retrieve_deleted = api_post("retrieve", {"collection": "test", "id": "test_001"})
test("Documento eliminado no se recupera", retrieve_deleted.get("status") == "not_found", json.dumps(retrieve_deleted))

# ------------------------------------------------------------
print("\n" + "="*60)
print(f"RESULTADOS: {PASSED} pasadas, {FAILED} fallidas")
print("="*60)

if FAILED > 0:
    print("\n❌ ALGUNAS PRUEBAS FALLARON")
    sys.exit(1)
else:
    print("\n✅ TODAS LAS PRUEBAS PASARON")
    sys.exit(0)
