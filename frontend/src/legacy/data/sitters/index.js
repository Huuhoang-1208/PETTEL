import { sitters1 } from "./sitters1";
import { sitters2 } from "./sitters2";
import { sitters3 } from "./sitters3";
import { sitters4 } from "./sitters4";
import { sitters5 } from "./sitters5";
import { sitters6 } from "./sitters6";
let seed = 42;
function random() {
  seed = (seed * 1664525 + 1013904223) >>> 0;
  return seed / 4294967296;
}
export const sittersData = [
  ...sitters1,
  ...sitters2,
  ...sitters3,
  ...sitters4,
  ...sitters5,
  ...sitters6,
];
sittersData.forEach((sitter) => {
  if (sitter.location && !sitter.district) {
    // Extract district from "District, City" format
    const parts = sitter.location.split(",");
    if (parts.length > 0) {
      sitter.district = parts[0].trim().toLowerCase();
    }
  }
});
const petSisterImages = [
  "/Pet sister/Teneo-WFA-Hero-2022.jpg",
  "/Pet sister/smiling-young-asian-woman-in-yellow-shirt-is-holding-a-white-ragdoll-cat-over-isolated-white-background-photo.jpg",
  "/Pet sister/Single-Services-of-Dog-Grooming_4-scaled.jpg",
  "/Pet sister/R.jpg",
  "/Pet sister/portrait-young-asian-woman-holding-600nw-2344067699.webp",
  "/Pet sister/portrait-beautiful-chinese-young-girl-hug-her-cute-british-shorthair-cat-who-has-amazing-orange-eyes-home-sunny-afternoon-141149436.webp",
  "/Pet sister/pexels-thirdman-9313652-1-1400x933.jpg",
  "/Pet sister/PDP_Sticks_LifestyleImage_GuyWithDog.webp",
  "/Pet sister/OIP.webp",
  "/Pet sister/MV5BNTMxNzE3NDUtMjBiMy00M2U4LTg3MTctMzY1Nzk3MDg4OGJkXkEyXkFqcGc@._V1_FMjpg_UX1000_.jpg",
  "/Pet sister/MTXX_MR20230613_153129205_1.webp",
  "/Pet sister/Labrador-retriever-sitting-on-the-couch-next-to-a-man-giving-a-high-five.jpg",
  "/Pet sister/istockphoto-665117590-612x612.jpg",
  "/Pet sister/istockphoto-174837615-170667a.jpg",
  "/Pet sister/istockphoto-1391961103-170667a.webp",
  "/Pet sister/istockphoto-1329541969-612x612.jpg",
  "/Pet sister/istockphoto-1285083459-612x612.jpg",
  "/Pet sister/istockphoto-1171217979-612x612.jpg",
  "/Pet sister/happy-asian-woman-enjoying-her-dog-pet-home-friendship-pet-human-lifestyle-concept_1137986-1678.avif",
  "/Pet sister/glenn-han-yjXXlapVYrQ-unsplash-min-1536x1024.jpg",
  "/Pet sister/dv2-1249-6330.jpg",
  "/Pet sister/Brunos-Play-Center-1000x750.jpg",
  "/Pet sister/artworks-9OVWQIWvyNL6cJd4-vF5eqQ-t500x500.jpg",
  "/Pet sister/5-ella-chen-data.jpg",
  "/Pet sister/585dd8967f02af6c1982394f472cd1f5.jpg",
  "/Pet sister/46e2e0_1f31434b61c34ff48d2175a16de6a09a~mv2.avif",
  "/Pet sister/2-1665279330.jpg",
  "/Pet sister/0d0eef1b4633a11610c34c1e1a236598.jpg",
];
const allServices = ["trongtaigia", "guigam", "datdidao", "cattialong", "vesinhspa"];
const districts = ["haichau", "camle", "sontra", "thanhkhe", "lienchiều", "ngu hanh son"];
const districtNames = ["Hải Châu", "Cẩm Lệ", "Sơn Trà", "Thanh Khê", "Liên Chiểu", "Ngũ Hành Sơn"];
const priceRanges = [
  { min: 50000, max: 99000 }, // 0-100k range
  { min: 100000, max: 199000 }, // 100-200k range
  { min: 200000, max: 299000 }, // 200-300k range
  { min: 300000, max: 399000 }, // 300-400k range
  { min: 400000, max: 499000 }, // 400-500k range
  { min: 500000, max: 700000 }, // 500k+ range
];
sittersData.forEach((sitter, index) => {
  // Add services - randomly assign 2-4 services to each sitter
  if (!sitter.services) {
    const numServices = Math.floor(random() * 3) + 2; // 2-4 services
    const shuffled = [...allServices].sort(() => 0.5 - random());
    sitter.services = shuffled.slice(0, numServices);
  }

  // Assign district - distribute evenly with some variation
  const districtIndex = index % districts.length;
  sitter.district = districts[districtIndex];
  sitter.location = `${districtNames[districtIndex]}, Đà Nẵng`;

  // Assign price - distribute across all ranges
  const priceRangeIndex = index % priceRanges.length;
  const range = priceRanges[priceRangeIndex];
  // Random price within the range
  sitter.price = Math.floor(random() * (range.max - range.min + 1)) + range.min;

  // Assign trust level based on price
  if (sitter.price < 100000) {
    sitter.trustLevel = "new";
    sitter.confidenceScore = Math.floor(random() * 20) + 60; // 60-80
    sitter.verifiedDetails = {
      idCheck: false,
      backgroundCheck: false,
      hasCertificate: false,
      verifiedReviewCount: 0,
      liveUpdates: false,
    };
  } else if (sitter.price >= 100000 && sitter.price < 200000) {
    sitter.trustLevel = "new"; // 100-200k also new member
    sitter.confidenceScore = Math.floor(random() * 20) + 60; // 60-80
    sitter.verifiedDetails = {
      idCheck: false,
      backgroundCheck: false,
      hasCertificate: false,
      verifiedReviewCount: 0,
      liveUpdates: false,
    };
  } else if (sitter.price >= 200000 && sitter.price < 400000) {
    sitter.trustLevel = "verified"; // 200-400k is verified
    sitter.confidenceScore = Math.floor(random() * 15) + 80; // 80-95
    sitter.verifiedDetails = {
      idCheck: true,
      backgroundCheck: true,
      hasCertificate: false,
      verifiedReviewCount: sitter.reviewCount || 50,
      liveUpdates: false,
    };
  } else {
    sitter.trustLevel = "pro"; // 400k+ is pro
    sitter.confidenceScore = Math.floor(random() * 10) + 90; // 90-100
    sitter.verifiedDetails = {
      idCheck: true,
      backgroundCheck: true,
      hasCertificate: true,
      verifiedReviewCount: sitter.reviewCount || 100,
      liveUpdates: true,
    };
  }

  // Update image from Pet sister folder
  sitter.image = petSisterImages[index % petSisterImages.length];
});
