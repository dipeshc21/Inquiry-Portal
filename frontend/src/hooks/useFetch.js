import { useCallback, useEffect, useRef, useState } from "react";

export default function useFetch(
  loader,
  dependencies = [],
  { enabled = true } = {},
) {
  const loaderRef = useRef(loader);
  const controllerRef = useRef(null);
  const requestIdRef = useRef(0);
  const mountedRef = useRef(false);

  const [data, setData] = useState(null);
  const [meta, setMeta] = useState({});
  const [loading, setLoading] = useState(enabled);
  const [error, setError] = useState(null);

  loaderRef.current = loader;

  useEffect(() => {
    mountedRef.current = true;

    return () => {
      mountedRef.current = false;
      requestIdRef.current += 1;
      controllerRef.current?.abort();
    };
  }, []);

  const refresh = useCallback(async () => {
    controllerRef.current?.abort();

    if (!enabled) {
      setLoading(false);
      return null;
    }

    const controller = new AbortController();
    controllerRef.current = controller;

    const requestId = ++requestIdRef.current;

    setLoading(true);
    setError(null);

    try {
      const result = await loaderRef.current(controller.signal);

      if (
        mountedRef.current &&
        requestId === requestIdRef.current
      ) {
        setData(result.data);
        setMeta(result.meta ?? {});
      }

      return result;
    } catch (requestError) {
      if (
        requestError.code !== "ERR_CANCELED" &&
        mountedRef.current &&
        requestId === requestIdRef.current
      ) {
        setError(requestError);
      }

      return null;
    } finally {
      if (
        mountedRef.current &&
        requestId === requestIdRef.current
      ) {
        setLoading(false);
      }
    }
  }, [enabled]);

  useEffect(() => {
    refresh();

    return () => {
      requestIdRef.current += 1;
      controllerRef.current?.abort();
    };
  }, [refresh, ...dependencies]);

  return {
    data,
    meta,
    loading,
    error,
    refresh,
    setData,
  };
}
