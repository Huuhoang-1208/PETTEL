export type OwnerProfile = {
  name: string; email: string; created_at: string | null; email_verified: boolean;
  phone: string | null; bio: string | null; area_code: string | null; avatar_media_id: string | null;
  emergency_contacts: EmergencyContacts | null;
  stats: { pet_count: number; booking_count: number; completed_count: number };
};

export type EmergencyContacts = {
  primary?: { name?: string | null; phone?: string | null };
  secondary?: { name?: string | null; phone?: string | null; relationship?: string | null; available_24h?: boolean };
  vet?: { name?: string | null; phone?: string | null; doctor?: string | null; address?: string | null };
};
