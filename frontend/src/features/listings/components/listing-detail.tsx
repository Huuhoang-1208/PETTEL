"use client";

import { PettelIcon } from "@/components/ui/pettel-icon";
import { RequireSession } from "@/features/auth/components/require-session";
import { BookingForm } from "@/features/listings/components/booking-form";
import { ListingCaregiverTrust } from "@/features/listings/components/listing-caregiver-trust";
import { ListingAvailabilityCard } from "@/features/listings/components/listing-availability-card";
import { ListingDetailHero } from "@/features/listings/components/listing-detail-hero";
import { ListingPricingCard } from "@/features/listings/components/listing-pricing-card";
import { ListingReviewsCard } from "@/features/listings/components/listing-reviews-card";
import { ListingSpaceCard } from "@/features/listings/components/listing-space-card";
import { ListingLocationCard } from "@/features/listings/components/listing-location-card";
import { type Listing } from "@/lib/api/types";
import { sizes, species } from "@/lib/constants";

export function ListingDetail({ listing }: { listing: Listing }) {
  const introduction =
    listing.caregiver?.demo?.introduction ?? listing.caregiver?.bio ?? listing.description;
  const principles = listing.caregiver?.care_principles?.length
    ? listing.caregiver.care_principles
    : (listing.caregiver?.demo?.care_principles ?? []);
  const experienceYears =
    listing.caregiver?.experience_years ?? listing.caregiver?.demo?.experience_years;
  const petRequirementLabels: Record<string, string> = {
    REQUIRE_VACCINATION: "Chỉ nhận bé đã tiêm phòng đầy đủ",
    ACCEPTS_MEDICATION: "Có thể chăm sóc bé cần uống thuốc",
    ACCEPTS_SENIOR_OR_SHY: "Có thể nhận bé lớn tuổi hoặc nhút nhát",
    ACCEPTS_SPECIAL_DIET: "Có thể theo chế độ ăn đặc biệt",
    ACCEPTS_POST_SURGERY: "Có thể chăm sóc sau phẫu thuật theo hướng dẫn thú y",
  };
  const petRequirements = (listing.caregiver?.pet_requirements ?? [])
    .map((requirement) => petRequirementLabels[requirement])
    .filter(Boolean);

  return (
    <>
      <ListingDetailHero listing={listing} />
      <div className="app-detail-grid">
        <article className="listing-detail-content">
          <ListingCaregiverTrust listing={listing} />

          <section className="app-card listing-detail-panel">
            <h2>
              Về {listing.caregiver?.display_name ?? "người chăm sóc"} &amp; Triết lý chăm sóc
            </h2>
            <p className="app-description">{introduction}</p>
            {experienceYears !== null && experienceYears !== undefined ? (
              <p className="listing-detail-experience">
                Kinh nghiệm chăm sóc thú cưng: {experienceYears} năm
              </p>
            ) : null}
            <h3>Thông tin chăm sóc</h3>
            <div className="listing-detail-facts">
              <p>
                <PettelIcon
                  name={listing.acceptance_rules_json.species.includes("DOG") ? "dog" : "paw"}
                />
                {listing.acceptance_rules_json.species.map((value) => species[value]).join(" · ")}
              </p>
              <p>
                Kích thước:{" "}
                {listing.acceptance_rules_json.sizes.map((value) => sizes[value]).join(" · ")}
              </p>
            </div>
            {petRequirements.length > 0 && (
              <>
                <h3>Yêu cầu &amp; khả năng nhận thú cưng</h3>
                <ul className="listing-pet-requirements">
                  {petRequirements.map((requirement) => (
                    <li key={requirement}>{requirement}</li>
                  ))}
                </ul>
              </>
            )}
            {principles.length > 0 && (
              <>
                <h3>3 nguyên tắc chăm sóc</h3>
                <div className="listing-care-principles">
                  {principles.slice(0, 3).map((principle, index) => (
                    <article key={`${principle.title}-${index}`}>
                      <strong>
                        <PettelIcon
                          name={index === 0 ? "clock" : index === 1 ? "walk" : "camera"}
                        />
                        {index + 1}. {principle.title}
                      </strong>
                      <p>{principle.description}</p>
                    </article>
                  ))}
                </div>
              </>
            )}
          </section>

          <ListingSpaceCard listing={listing} />
          <ListingPricingCard listing={listing} />
          <ListingAvailabilityCard listing={listing} />

          <ListingReviewsCard key={listing.id} listing={listing} />
          <ListingLocationCard listing={listing} />
        </article>

        <aside className="app-card app-booking">
          <RequireSession next={`/detail?id=${listing.id}`}>
            <BookingForm listing={listing} />
          </RequireSession>
        </aside>
      </div>
    </>
  );
}
