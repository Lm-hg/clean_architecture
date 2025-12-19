import { useState } from 'react';
import { toast } from 'sonner';

export function useAsyncOperation<T = any>() {
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const execute = async (operation: () => Promise<T>): Promise<T | null> => {
    setLoading(true);
    setError(null);
    
    try {
      const result = await operation();
      setLoading(false);
      return result;
    } catch (err: any) {
      const errorMessage = err?.message || 'Une erreur est survenue';
      setError(errorMessage);
      setLoading(false);
      toast.error(errorMessage);
      return null;
    }
  };

  return { loading, error, execute };
}
