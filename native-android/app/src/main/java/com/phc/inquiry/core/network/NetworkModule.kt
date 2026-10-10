package com.phc.inquiry.core.network

import com.phc.inquiry.BuildConfig
import com.phc.inquiry.core.security.*
import com.phc.inquiry.data.*
import dagger.Binds
import dagger.Module
import dagger.Provides
import dagger.hilt.InstallIn
import dagger.hilt.components.SingletonComponent
import java.util.concurrent.TimeUnit
import javax.inject.Singleton
import kotlinx.serialization.json.Json
import okhttp3.HttpUrl.Companion.toHttpUrl
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.OkHttpClient
import retrofit2.Retrofit
import retrofit2.converter.kotlinx.serialization.asConverterFactory

@Module
@InstallIn(SingletonComponent::class)
abstract class RepositoryModule {
    @Binds @Singleton abstract fun store(store: KeystoreSessionStore): SessionStore
    @Binds @Singleton abstract fun repository(repository: LaravelInquiryRepository): InquiryRepository
}

@Module
@InstallIn(SingletonComponent::class)
object NetworkModule {
    @Provides @Singleton fun policy(): TransportPolicy = TransportPolicy(BuildConfig.API_BASE_URL.toHttpUrl(), BuildConfig.ALLOW_HTTP)

    @Provides @Singleton fun api(policy: TransportPolicy, session: SessionState): InquiryApi {
        val client = OkHttpClient.Builder()
            .connectTimeout(15, TimeUnit.SECONDS).readTimeout(20, TimeUnit.SECONDS).callTimeout(30, TimeUnit.SECONDS)
            .followRedirects(false).followSslRedirects(false).retryOnConnectionFailure(false)
            .addInterceptor(InquiryInterceptor(policy, session::token)).build()
        val json = Json { ignoreUnknownKeys = true; coerceInputValues = true }
        return Retrofit.Builder().baseUrl(policy.baseUrl).client(client)
            .addConverterFactory(json.asConverterFactory("application/json".toMediaType())).build().create(InquiryApi::class.java)
    }
}
