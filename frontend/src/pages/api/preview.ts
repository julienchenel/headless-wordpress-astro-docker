import type { APIRoute } from 'astro';
import { JWT } from 'firebase/auth'; // ou 'jsonwebtoken' si tu préfères
import { rewriteUrls } from '../../utils/rewriteUrls';

export const GET: APIRoute = async ({ request }) => {
  const url = new URL(request.url);
  const token = url.searchParams.get('token');
  const postType = url.searchParams.get('post_type');
  const postName = url.searchParams.get('post_name');

  if (!token || !postType || !postName) {
    return new Response(JSON.stringify({ error: 'Missing parameters' }), {
      status: 400,
      headers: { 'Content-Type': 'application/json' },
    });
  }

  try {
    // Vérifier le token JWT
    const decoded = JWT.verify(token, import.meta.env.VITE_JWT_SECRET) as {
      post_id: number;
      user_id: number;
      exp: number;
    };

    if (!decoded.post_id) {
      throw new Error('Invalid token');
    }

    // Récupérer les données depuis Headless Bridge
    const response = await fetch(
      `${import.meta.env.VITE_WP_API_URL}/preview/${postType}/${postName}?token=${token}`
    );

    if (!response.ok) {
      throw new Error('Failed to fetch preview data');
    }

    const data = await response.json();

    // Réécrire les URLs
    const rewrittenData = rewriteUrls(JSON.stringify(data));

    return new Response(rewrittenData, {
      status: 200,
      headers: { 'Content-Type': 'application/json' },
    });
  } catch (error) {
    return new Response(JSON.stringify({ error: 'Invalid token or expired' }), {
      status: 401,
      headers: { 'Content-Type': 'application/json' },
    });
  }
};
